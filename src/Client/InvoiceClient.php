<?php

namespace ExcelleInsights\QuickBooks\Client;

use ExcelleInsights\QuickBooks\Auth\Authentication;

class InvoiceClient extends BaseClient
{
    public function __construct(
        string $baseUrl,
        string $companyId,
        Authentication $auth
    ) {
        parent::__construct($baseUrl, $companyId, $auth);
    }

    /**
     * Create invoice in QuickBooks Online
     */
    public function create(array $data): object
    {
        $payload = [
            'CustomerRef' => [
                'value' => $data['qbo_customer_id'],
            ],
            'TxnDate' => $data['txn_date'] ?? date('Y-m-d'),
            'PrivateNote' => $data['notes'] ?? '',
            'Line' => $this->buildLines($data['items'] ?? []),
        ];

        return $this->sendRequest('POST', $this->endpoint('invoice'), $payload);
    }

    /**
     * Retrieve invoice by QBO ID
     */
    public function getById(string $qboInvoiceId): object
    {
        return $this->sendRequest('GET', $this->endpoint('invoice/' . urlencode($qboInvoiceId)));
    }

    /**
     * Search invoice by invoice number
     */
    public function search(string $invoiceNumber): object
    {
        $query = "select Id from Invoice Where DocNumber = '" . trim($invoiceNumber) . "'";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . urlencode($query)));
    }

    /**
     * Void / deactivate invoice
     */
    public function void(string $qboInvoiceId, string $syncToken): object
    {
        $payload = [
            'Id' => $qboInvoiceId,
            'SyncToken' => $syncToken,
            'sparse' => true,
            'PrivateNote' => 'Voided locally',
        ];

        return $this->sendRequest('POST', $this->endpoint('invoice'), $payload);
    }

    /**
     * Convert local items to QBO Line objects
     */
    private function buildLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = [
                'DetailType' => 'SalesItemLineDetail',
                'Amount' => (float) $item['amount'],
                'Description' => $item['description'] ?? '',
                'SalesItemLineDetail' => [
                    "ItemRef" => [
                        "value" => isset($item['item_id']) ? $item['item_id'] : "",
                        "name" => isset($item['item_name']) ? $item['item_name'] : ""
                    ],
                    'Qty' => (float) $item['quantity'] ?? 1,
                    'UnitPrice' => (float) $item['unit_price'] ?? 0,
                ],
            ];
        }

        return $lines;
    }
}
