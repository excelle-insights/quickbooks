<?php

namespace ExcelleInsights\QuickBooks\Client;

class JournalEntryClient extends BaseClient
{
    /**
     * Create a new Journal Entry in QuickBooks
     */
    public function create(array $data): object
    {
        if (empty($data['lines']) || !is_array($data['lines'])) {
            throw new \InvalidArgumentException('Journal entry lines are required.');
        }

        $payload = array_filter([
            'TxnDate'    => $data['txn_date'] ?? date('Y-m-d'),
            'DocNumber' => $data['doc_number'] ?? null,
            'PrivateNote' => $data['notes'] ?? null,
            'CurrencyRef' => isset($data['currency'])
                ? ['value' => $data['currency']]
                : null,
            'Line' => $this->buildLines($data['lines']),
        ], fn($v) => $v !== null);

        return $this->sendRequest(
            'POST',
            $this->endpoint('journalentry'),
            $payload
        );
    }

    /**
     * Retrieve a Journal Entry by QBO ID
     */
    public function getById(string $qboJournalEntryId): object
    {
        return $this->sendRequest(
            'GET',
            $this->endpoint('journalentry/' . urlencode($qboJournalEntryId))
        );
    }

    /**
     * Get the DocNumber of the most recent journal entry (by TxnDate).
     * Returns null when no journal entries exist or none have a DocNumber.
     */
    public function getLastDocNumber(): ?string
    {
        $query = 'SELECT * FROM JournalEntry ORDERBY TxnDate DESC MAXRESULTS 1';

        $response = $this->sendRequest(
            'GET',
            $this->endpoint('query?query=' . rawurlencode($query))
        );

        $entry = $response->QueryResponse->JournalEntry[0] ?? null;

        return isset($entry->DocNumber) && $entry->DocNumber !== ''
            ? (string) $entry->DocNumber
            : null;
    }

    /**
     * Search journal entries by DocNumber
     */
    public function searchByDocNumber(string $docNumber): object
    {
        $query = "SELECT * FROM JournalEntry WHERE DocNumber = '" . trim($docNumber) . "'";

        return $this->sendRequest(
            'GET',
            $this->endpoint('query?query=' . rawurlencode($query))
        );
    }

    /**
     * Retrieve all journal entries, optionally only those updated since a given time.
     *
     * @param int         $startPosition 1-based page start
     * @param int         $maxResults    Page size (QBO maximum is 1000)
     * @param string|null $updatedSince  QBO-formatted timestamp, e.g. 2024-01-01T00:00:00-07:00
     */
    public function getAll(int $startPosition = 1, int $maxResults = 1000, ?string $updatedSince = null): object
    {
        $query = 'SELECT * FROM JournalEntry';

        if ($updatedSince !== null && trim($updatedSince) !== '') {
            $query .= " WHERE MetaData.LastUpdatedTime >= '" . trim($updatedSince) . "'";
        }

        $query .= ' STARTPOSITION ' . $startPosition . ' MAXRESULTS ' . $maxResults;

        return $this->sendRequest(
            'GET',
            $this->endpoint('query?query=' . rawurlencode($query))
        );
    }

    /**
     * Update a journal entry via sparse update in QuickBooks
     */
    public function update(string $qboJournalEntryId, string $syncToken, array $data): object
    {
        if ($syncToken === '' || $syncToken === null) {
            throw new \InvalidArgumentException('syncToken is required to update a journal entry.');
        }

        $payload = array_filter([
            'Id'          => $qboJournalEntryId,
            'SyncToken'   => $syncToken,
            'sparse'      => true,
            'TxnDate'     => $data['txn_date'] ?? null,
            'DocNumber'   => $data['doc_number'] ?? null,
            'PrivateNote' => $data['notes'] ?? null,
            'CurrencyRef' => isset($data['currency'])
                ? ['value' => $data['currency']]
                : null,
            'Line'        => !empty($data['lines'])
                ? $this->buildLines($data['lines'])
                : null,
        ], fn($v) => $v !== null);

        return $this->sendRequest('POST', $this->endpoint('journalentry'), $payload);
    }

    /**
     * Build QBO journal entry lines
     */
    private function buildLines(array $lines): array
    {
        $payloadLines = [];

        foreach ($lines as $line) {
            if (
                empty($line['account_qbo_id']) ||
                (empty($line['debit']) && empty($line['credit']))
            ) {
                throw new \InvalidArgumentException(
                    'Each journal entry line requires account_qbo_id and either debit or credit.'
                );
            }

            $amount = !empty($line['debit'])
                ? (float) $line['debit']
                : (float) $line['credit'];

            $postingType = !empty($line['debit'])
                ? 'Debit'
                : 'Credit';

            $lineDetail = array_filter([
                'PostingType' => $postingType,
                'AccountRef' => array_filter([
                    'value' => $line['account_qbo_id'],
                    'name'  => $line['account_name'] ?? null,
                ], fn($v) => $v !== null),
            ], fn($v) => $v !== null);

            if (!empty($line['entity'])) {
                $lineDetail['Entity'] = [
                    'Type'      => $line['entity']['type'] ?? 'Customer',
                    'EntityRef' => ['value' => $line['entity']['value']],
                ];
            }

            $payloadLines[] = array_filter([
                'DetailType' => 'JournalEntryLineDetail',
                'Amount'    => $amount,
                'Description' => $line['description'] ?? null,
                'JournalEntryLineDetail' => $lineDetail,
            ], fn($v) => $v !== null);
        }

        return $payloadLines;
    }
}
