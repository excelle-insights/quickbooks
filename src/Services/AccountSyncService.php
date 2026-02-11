<?php

namespace ExcelleInsights\QuickBooks\Services;

use ExcelleInsights\QuickBooks\Client\AccountClient;
use ExcelleInsights\QuickBooks\Repositories\QboAccountRepository;

class AccountSyncService
{
    public function __construct(
        private QboAccountRepository $accounts,
        private AccountClient $qbo
    ) {}

    /**
     * Create account locally, then sync to QBO
     */
    public function create(array $data): object
    {
        // 1. Create locally (source of truth)
        $localId = $this->accounts->create($data);

        try {
            // 2. Create in QuickBooks
            $response = $this->qbo->create($data);

            if (!isset($response->Account)) {
                throw new \RuntimeException('Invalid QBO response');
            }

            // 3. Link local ↔ QBO
            $this->accounts->markSynced(
                $localId,
                $response->Account->Id,
                $response->Account->SyncToken
            );

            return (object) [
                'status'   => 'synced',
                'local_id' => $localId,
                'qbo_id'   => $response->Account->Id,
                'data'     => $response->Account
            ];

        } catch (\Throwable $e) {
            error_log("QBO Account sync failed: " . $e->getMessage());

            // 4. Mark failed (retry later)
            $this->accounts->markFailed(
                $localId,
                $e->getMessage()
            );

            return (object) [
                'status'   => 'pending',
                'local_id' => $localId,
                'error'    => $e->getMessage()
            ];
        }
    }
}
