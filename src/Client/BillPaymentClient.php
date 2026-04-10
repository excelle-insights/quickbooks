<?php

namespace ExcelleInsights\QuickBooks\Client;

class BillPaymentClient extends BaseClient
{
    /**
     * Create a new BillPayment in QuickBooks
     */
    public function create(array $data): object
    {
        if (empty($data['vendor_qbo_id'])) {
            throw new \InvalidArgumentException('vendor_qbo_id is required to create a bill payment.');
        }

        if (empty($data['total_amount'])) {
            throw new \InvalidArgumentException('Payment amount is required.');
        }

        if (empty($data['bill_payments']) || !is_array($data['bill_payments'])) {
            throw new \InvalidArgumentException('Bill payments are required.');
        }

        $lineItemData = [];
        foreach ($data['bill_payments'] as $billPayment) {
            if (empty($billPayment['qbo_bill_id']) || empty($billPayment['amount'])) {
                continue; // skip invalid line
            }

            $lineItemData[] = [
                'Amount' => (float) $billPayment['amount'],
                'LinkedTxn' => [
                    [
                        'TxnId' => $billPayment['qbo_bill_id'],
                        'TxnType' => 'Bill'
                    ]
                ]
            ];
        }

        if (empty($lineItemData)) {
            throw new \InvalidArgumentException('No valid bill payments found.');
        }

        // Build the payload according to QuickBooks API structure
        // CheckPayment is required for BillPayment
        $payload = [
            'VendorRef' => [
                'value' => $data['vendor_qbo_id']
            ],
            'TotalAmt' => (float) $data['total_amount'],
            'TxnDate' => $data['txn_date'] ?? date('Y-m-d'),
            'PayType' => 'Check',
            'CheckPayment' => [
                'BankAccountRef' => [
                    'value' => $data['bank_account_qbo_id'] ?? '1'
                ]
            ],
            'Line' => $lineItemData
        ];

        // Add private note if provided
        if (!empty($data['memo'])) {
            $payload['PrivateNote'] = $data['memo'];
        }

        return $this->sendRequest('POST', $this->endpoint('billpayment'), $payload);
    }

    /**
     * Retrieve a bill payment by QBO ID
     */
    public function getById(string $qboBillPaymentId): object
    {
        return $this->sendRequest(
            'GET',
            $this->endpoint('billpayment/' . urlencode($qboBillPaymentId))
        );
    }
}