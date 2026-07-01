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
