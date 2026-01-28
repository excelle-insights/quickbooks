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

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new \InvalidArgumentException('Line items are required for payment.');
        }

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

        if (empty($lineItemData)) {
            throw new \InvalidArgumentException('No valid line items to create payment.');
        }

        $payload = array_filter([
            'CustomerRef' => [
                'value' => $data['customer_ref'] ?? 'KES'
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
