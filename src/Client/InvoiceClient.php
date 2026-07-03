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
            'Line'        => $this->buildLines($data['items']),
            // 'TxnTaxDetail' => [
            //     "TxnTaxCodeRef" => [
            //         'value' => 6
            //     ]
            // ]
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
     * Get all invoices with outstanding balances for a specific customer
     */
    public function getByCustomerWithBalance(string $customerQboId, int $maxResults = 1000, int $startPosition = 1): object
    {
        $query = "SELECT * FROM Invoice WHERE CustomerRef = '$customerQboId' AND Balance > '0' STARTPOSITION $startPosition MAXRESULTS $maxResults";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }

    /**
     * Get all invoices for a specific customer (regardless of balance)
     */
    public function getByCustomer(string $customerQboId, int $maxResults = 1000, int $startPosition = 1): object
    {
        $query = "SELECT * FROM Invoice WHERE CustomerRef = '$customerQboId' STARTPOSITION $startPosition MAXRESULTS $maxResults";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }
    /**
     * Get all invoices for a customer after a specific date
     */
    public function getByCustomerAfterDate(string $customerQboId, string $afterDate, int $maxResults = 1000, int $startPosition = 1): object
    {
        $query = "SELECT * FROM Invoice WHERE CustomerRef = '$customerQboId' AND TxnDate >= '$afterDate' STARTPOSITION $startPosition MAXRESULTS $maxResults";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
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
     * Void an invoice in QuickBooks Online
     *
     * POST /invoice?operation=void&minorversion=75
     * with body { SyncToken, Id }
     */
    public function void(string $qboInvoiceId, string $syncToken): object
    {
        $payload = [
            'Id'        => $qboInvoiceId,
            'SyncToken' => $syncToken,
        ];

        return $this->sendRequest('POST', $this->endpoint('invoice?operation=void'), $payload);
    }

    /**
     * Update an invoice via sparse update in QuickBooks
     */
    public function update(string $qboInvoiceId, string $syncToken, array $data): object
    {
        if ($syncToken === '' || $syncToken === null) {
            throw new \InvalidArgumentException('syncToken is required to update an invoice.');
        }

        $payload = array_filter([
            'Id'          => $qboInvoiceId,
            'SyncToken'   => $syncToken,
            'sparse'      => true,
            'TxnDate'     => $data['txn_date'] ?? null,
            'DueDate'     => $data['due_date'] ?? null,
            'DocNumber'   => $data['invoice_number'] ?? null,
            'PrivateNote' => $data['notes'] ?? null,
            'CustomerRef' => isset($data['customer_qbo_id'])
                ? ['value' => $data['customer_qbo_id']]
                : null,
            'Line'        => !empty($data['items'])
                ? $this->buildLines($data['items'])
                : null,
        ], fn($v) => $v !== null);

        return $this->sendRequest('POST', $this->endpoint('invoice'), $payload);
    }

    /**
     * Build QBO line items from local items
     */
    private function buildLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {

            $salesItemDetail = [];

            // Only include ItemRef if it has a value
            if (!empty($item['item_id'])) {
                $salesItemDetail['ItemRef'] = [
                    'value' => $item['item_id']
                ];
            }

            $salesItemDetail['Qty'] = isset($item['quantity'])
                ? (float) $item['quantity']
                : 1;

            $salesItemDetail['UnitPrice'] = isset($item['unit_price'])
                ? (float) $item['unit_price']
                : 0;

            // Add ClassRef only if valid
            if (!empty($item['class_qbo_id'])) {
                $salesItemDetail['ClassRef'] = [
                    'value' => $item['class_qbo_id']
                ];
            }

            // Add TaxCodeRef only if valid
            if (!empty($item['tax_qbo_id'])) {
                $salesItemDetail['TaxCodeRef'] = [
                    'value' => $item['tax_qbo_id']
                ];
            }

            $lines[] = [
                'DetailType' => 'SalesItemLineDetail',
                'Amount'     => (float) $item['amount'],
                'Description' => $item['description'] ?? null,
                'SalesItemLineDetail' => $salesItemDetail
            ];
        }

        return $lines;
    }
}
