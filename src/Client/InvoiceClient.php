<?php

namespace ExcelleInsights\QuickBooks\Client;

use ExcelleInsights\QuickBooks\Auth\Authentication;

class InvoiceClient extends BaseClient
{
    /**
     * Create a new Invoice in QuickBooks
     */
    public function create(array $data): object
    {
        if (empty($data['customer_qbo_id'])) {
            throw new \InvalidArgumentException('customer_qbo_id is required to create an invoice.');
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new \InvalidArgumentException('Invoice items are required.');
        }

        $payload = array_filter([
            'CustomerRef' => [
                'value' => $data['customer_qbo_id']
            ],
            'DocNumber'   => $data['invoice_number'] ?? null,
            'DueDate'     => $data['txn_date'] ?? date('Y-m-d'),
            'TxnDate'     => $data['txn_date'] ?? date('Y-m-d'),
            'PrivateNote' => $data['notes'] ?? null,
            'Line'        => $this->buildLines($data['items'])
        ], fn($v) => $v !== null);

        return $this->sendRequest('POST', $this->endpoint('invoice'), $payload);
    }

    /**
     * Retrieve an invoice by QBO ID
     */
    public function getById(string $qboInvoiceId): object
    {
        return $this->sendRequest('GET', $this->endpoint('invoice/' . urlencode($qboInvoiceId)));
    }

    /**
     * Search invoice by DocNumber
     */
    public function search(string $invoiceNumber): object
    {
        $query = "select Id from Invoice Where DocNumber = '" . trim($invoiceNumber) . "'";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }

    /**
     * Void or deactivate an invoice
     */
    public function void(string $qboInvoiceId, string $syncToken): object
    {
        if (empty($syncToken)) {
            throw new \InvalidArgumentException('syncToken is required to void an invoice.');
        }

        $payload = [
            'Id'        => $qboInvoiceId,
            'SyncToken' => $syncToken,
            'sparse'    => true,
            'PrivateNote' => 'Voided locally'
        ];

        return $this->sendRequest('POST', $this->endpoint('invoice'), $payload);
    }

    /**
     * Build QBO line items from local items
     */
    private function buildLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = array_filter([
                'DetailType' => 'SalesItemLineDetail',
                'Amount'     => isset($item['amount']) ? (float) $item['amount'] : 0,
                'Description' => $item['description'] ?? null,
                'SalesItemLineDetail' => array_filter([
                    'ItemRef' => array_filter([
                        'value' => $item['item_id'] ?? null,
                        'name'  => $item['item_name'] ?? null
                    ], fn($v) => $v !== null),
                    'Qty'       => isset($item['quantity']) ? (float) $item['quantity'] : 1,
                    'UnitPrice' => isset($item['unit_price']) ? (float) $item['unit_price'] : 0,
                    'TaxCodeRef' => [
                        'value' => $item['tax_code'] ?? 'NON'
                    ]
                ])
            ], fn($v) => $v !== null);
        }

        return $lines;
    }
}
