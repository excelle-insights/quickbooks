<?php

namespace ExcelleInsights\QuickBooks\Client;

use ExcelleInsights\QuickBooks\Auth\Authentication;

class PaymentClient extends BaseClient
{
    /**
     * Create a new Payment in QuickBooks
     */
    public function create(array $data): object
    {
        if (empty($data['customer_qbo_id'])) {
            throw new \InvalidArgumentException('customer_qbo_id is required to create a payment.');
        }

        if (empty($data['amount'])) {
            throw new \InvalidArgumentException('Payment amount is required.');
        }

        // if (empty($data['items']) || !is_array($data['items'])) {
        //     throw new \InvalidArgumentException('Line items are required for payment.');
        // }

        $lineItemData = [];
        foreach ($data['items'] as $lineItem) {
            $lineItem = (array) $lineItem;
            if (empty($lineItem['qbo_invoice_id']) || empty($lineItem['amount'])) {
                continue; // skip invalid line
            }

            $lineItemData[] = [
                'Amount' => (float) $lineItem['amount'],
                'LinkedTxn' => [
                    [
                        'TxnId' => $lineItem['qbo_invoice_id'],
                        'TxnType' => 'Invoice'
                    ]
                ]
            ];
        }

        // if (empty($lineItemData)) {
        //     throw new \InvalidArgumentException('No valid line items to create payment.');
        // }

        $payload = array_filter([
            'CustomerRef' => [
                'value' => $data['customer_qbo_id']
            ],
            'TotalAmt' => (float) $data['amount'],
            'TxnDate' => $data['txn_date'] ?? date('Y-m-d'),
            'PaymentRefNum' => $data['transaction_ref'] ?? null,
            'DepositToAccountRef' => [
                'value' => $data['bank_account'] ?? null
            ],
            'Line' => $lineItemData,
            'PrivateNote' => $data['private_note'] ?? null
        ], fn($v) => $v !== null);

        return $this->sendRequest('POST', $this->endpoint('payment'), $payload);
    }

    /**
     * Retrieve a payment by QBO ID
     */
    public function getById(string $qboPaymentId): object
    {
        return $this->sendRequest(
            'GET',
            $this->endpoint('payment/' . urlencode($qboPaymentId))
        );
    }
    /**
     * Get all payments for a specific customer
     */
    public function getByCustomer(string $customerQboId, int $maxResults = 1000, int $startPosition = 1): object
    {
        $query = "SELECT * FROM Payment WHERE CustomerRef = '$customerQboId' STARTPOSITION $startPosition MAXRESULTS $maxResults";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }
    /**
     * Get all payments for a customer after a specific date
     */
    public function getByCustomerAfterDate(string $customerQboId, string $afterDate, int $maxResults = 1000, int $startPosition = 1): object
    {
        $query = "SELECT * FROM Payment WHERE CustomerRef = '$customerQboId' AND TxnDate >= '$afterDate' STARTPOSITION $startPosition MAXRESULTS $maxResults";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }
    /**
     * Search payment by reference number
     */
    public function search(string $paymentRefNum): object
    {
        $query = "select Id from Payment Where PaymentRefNum = '" . trim($paymentRefNum) . "'";
        return $this->sendRequest(
            'GET',
            $this->endpoint('query?query=' . rawurlencode($query))
        );
    }

    /**
     * Update a payment via sparse update in QuickBooks
     */
    public function update(string $qboPaymentId, string $syncToken, array $data): object
    {
        if (empty($syncToken)) {
            throw new \InvalidArgumentException('syncToken is required to update a payment.');
        }

        $payload = array_filter([
            'Id' => $qboPaymentId,
            'SyncToken' => $syncToken,
            'sparse' => true,
            'TotalAmt' => isset($data['total_amount']) ? (float) $data['total_amount'] : null,
            'TxnDate' => $data['txn_date'] ?? null,
            'PaymentRefNum' => $data['payment_ref'] ?? null,
            'DepositToAccountRef' => isset($data['deposit_account_id'])
                ? ['value' => $data['deposit_account_id']]
                : null,
            'PrivateNote' => $data['private_note'] ?? null,
        ], fn($v) => $v !== null);

        return $this->sendRequest('POST', $this->endpoint('payment'), $payload);
    }

    /**
     * Void or deactivate a payment
     */
    public function void(string $qboPaymentId, string $syncToken): object
    {
        if (empty($syncToken)) {
            throw new \InvalidArgumentException('syncToken is required to void a payment.');
        }

        $payload = [
            'Id' => $qboPaymentId,
            'SyncToken' => $syncToken,
            'sparse' => true,
            'PrivateNote' => 'Voided locally'
        ];

        return $this->sendRequest('POST', $this->endpoint('payment'), $payload);
    }
}
