<?php

namespace ExcelleInsights\QuickBooks\Services;

use ExcelleInsights\QuickBooks\Client\JournalEntryClient;
use ExcelleInsights\QuickBooks\Repositories\QboJournalEntryRepository;
use ExcelleInsights\QuickBooks\Repositories\QboJournalEntryLineRepository;

class JournalEntrySyncService
{
    public function __construct(
        private QboJournalEntryRepository $entries,
        private QboJournalEntryLineRepository $lines,
        private JournalEntryClient $qbo
    ) {}

    /**
     * Update & sync a Journal Entry
     */
    public function update(array $data): object
    {
        $localId = $data['local_id'] ?? null;
        if (!$localId) {
            return (object) ['status' => 'error', 'error' => 'local_id is required'];
        }

        $existing = $this->entries->findByLocalId($localId);
        if (!$existing) {
            return (object) ['status' => 'error', 'error' => 'Journal entry not found'];
        }

        // Replace lines if provided
        if (!empty($data['lines'])) {
            $this->lines->deleteByJournalEntry((int) $existing->id);
            foreach ($data['lines'] as $line) {
                $this->lines->create((int) $existing->id, $line);
            }
        }

        $this->entries->update((int) $existing->id, $data);

        try {
            $qboJE = $this->qbo->getById($existing->qbo_id);
            $syncToken = $qboJE->JournalEntry->SyncToken ?? null;

            $qboResult = $this->qbo->update(
                $existing->qbo_id,
                $syncToken,
                $data
            );

            $newSyncToken = $qboResult->JournalEntry->SyncToken ?? $syncToken;

            $this->entries->markSynced(
                (int) $existing->id,
                $existing->qbo_id,
                $newSyncToken
            );

            return (object) [
                'status'   => 'synced',
                'local_id' => $localId,
                'qbo_id'   => $existing->qbo_id,
            ];
        } catch (\Throwable $e) {
            error_log('QBO JournalEntry update failed: ' . $e->getMessage());
            return (object) [
                'status'   => 'failed',
                'local_id' => $localId,
                'error'    => $e->getMessage(),
            ];
        }
    }

    /**
     * Create & sync a Journal Entry
     */
    public function create(array $data): object
    {
        // 0️⃣ Prevent duplicate: skip if local_id already exists
        $existing = $this->entries->findByLocalId($data['local_id']);
        if ($existing) {
            return (object) [
                'status'   => 'duplicate',
                'local_id' => $existing->id,
                'qbo_id'   => $existing->qbo_id,
                'existing' => $existing,
            ];
        }

        // 1️⃣ Create local journal entry header
        $journalEntryId = $this->entries->create($data);

        // 2️⃣ Store journal entry lines locally
        foreach ($data['lines'] as $line) {
            $this->lines->create($journalEntryId, $line);
        }

        try {
            // 3️⃣ Create in QuickBooks (client builds payload)
            $response = $this->qbo->create($data);

            if (!isset($response->JournalEntry)) {
                throw new \RuntimeException('Invalid QBO JournalEntry response');
            }

            // 4️⃣ Mark as synced
            $this->entries->markSynced(
                $journalEntryId,
                $response->JournalEntry->Id,
                $response->JournalEntry->SyncToken
            );

            return (object) [
                'status'          => 'synced',
                'local_id'        => $journalEntryId,
                'source_local_id' => $data['local_id'],
                'qbo_id'          => $response->JournalEntry->Id,
                'journal_entry'   => $response->JournalEntry
            ];

        } catch (\Throwable $e) {
            error_log('QBO JournalEntry sync failed: ' . $e->getMessage());

            // 5️⃣ Mark failure for retry
            $this->entries->markFailed(
                $journalEntryId,
                $e->getMessage()
            );

            return (object) [
                'status'          => 'pending',
                'local_id'        => $journalEntryId,
                'source_local_id' => $data['local_id'],
                'error'           => $e->getMessage()
            ];
        }
    }
}
