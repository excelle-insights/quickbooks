<?php

namespace ExcelleInsights\QuickBooks\Client;

use ExcelleInsights\QuickBooks\Auth\Authentication;

class BillClient extends BaseClient
{
    /**
     * Create a new Bill in QuickBooks Online
     */
    public function create(array $data): object
    {
        if (empty($data['vendor_qbo_id'])) {
            throw new \InvalidArgumentException('vendor_qbo_id is required to create a Bill.');
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new \InvalidArgumentException('Bill items are required.');
        }

        $payload = array_filter([
            'VendorRef' => [
                'value' => $data['vendor_qbo_id']
            ],
            'TxnDate'   => $data['txn_date'] ?? date('Y-m-d'),
            'CurrencyRef' => $data['currency'] ?? null,
            'Line'      => $this->buildLines($data['items'])
        ], fn($v) => $v !== null);

        return $this->sendRequest('POST', $this->endpoint('bill'), $payload);
    }

    /**
     * Retrieve a Bill by QBO ID
     */
    public function getById(string $qboBillId): object
    {
        return $this->sendRequest('GET', $this->endpoint('bill/' . urlencode($qboBillId)));
    }

    /**
     * Void or delete a Bill
     */
    public function void(string $qboBillId, string $syncToken): object
    {
        if (empty($syncToken)) {
            throw new \InvalidArgumentException('syncToken is required to void a Bill.');
        }

        $payload = [
            'Id'        => $qboBillId,
            'SyncToken' => $syncToken,
            'sparse'    => true,
            'PrivateNote' => 'Voided locally'
        ];

        return $this->sendRequest('POST', $this->endpoint('bill'), $payload);
    }

    /**
     * Build QBO line items from local bill items
     */
    private function buildLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $detail = [
                'AccountRef' => [
                    'value' => $item['account_qbo_id']
                ]
            ];

            // Include ClassRef if resolved
            if (!empty($item['class_qbo_id'])) {
                $detail['ClassRef'] = [
                    'value' => $item['class_qbo_id']
                ];
            }

            // Include TaxCodeRef if resolved (e.g. VAT, EXEMPT, ZERO)
            if (!empty($item['tax_code_qbo_id'])) {
                $detail['TaxCodeRef'] = [
                    'value' => $item['tax_code_qbo_id']
                ];
            }

            $line = [
                'DetailType'                    => 'AccountBasedExpenseLineDetail',
                'Amount'                        => isset($item['amount']) ? (float) $item['amount'] : 0,
                'AccountBasedExpenseLineDetail' => $detail,
            ];

            if (!empty($item['description'])) {
                $line['Description'] = $item['description'];
            }

            $lines[] = $line;
        }

        return $lines;
    }
}
