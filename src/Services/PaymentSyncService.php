<?php

namespace ExcelleInsights\QuickBooks\Services;

use ExcelleInsights\QuickBooks\Repositories\QboPaymentRepository;
use ExcelleInsights\QuickBooks\Repositories\QboPaymentItemRepository;
use ExcelleInsights\QuickBooks\Repositories\QboCustomerRepository;
use ExcelleInsights\QuickBooks\Repositories\QboInvoiceRepository;
use ExcelleInsights\QuickBooks\Client\PaymentClient;

class PaymentSyncService
{
    public function __construct(
        private QboPaymentRepository $paymentRepo,
        private QboPaymentItemRepository $paymentItemRepo,
        private QboCustomerRepository $customerRepo,
        private QboInvoiceRepository $invoiceRepo,
        private PaymentClient $paymentClient
    ) {}

    /**
     * Create payment locally and attempt sync with QuickBooks Online
     */
    public function create(array $data): object
    {
        /**
         * 1️⃣ Create payment locally
         */
        $localPaymentId = $this->paymentRepo->create($data);

        /**
         * 2️⃣ Persist line items locally
         */
        foreach ($data['items'] ?? [] as $item) {
            $item['qbo_payment_id'] = $localPaymentId;
            $this->paymentItemRepo->create($item);
        }

        /**
         * 3️⃣ Load customer (local source of truth)
         */
        $customer = $this->customerRepo->find(
            (int) $data['qbo_customer_id']
        );

        if (!$customer || !$customer->qbo_id) {
            return (object) [
                'status'   => 'queued',
                'local_id' => $localPaymentId,
                'reason'   => 'Customer not yet synced to QBO',
            ];
        }

        /**
         * 4️⃣ Ensure all linked invoices are synced
         */
        $lineItems = $this->paymentItemRepo->getByPaymentId($localPaymentId);

        foreach ($lineItems as $item) {
            $invoice = $this->invoiceRepo->find((int) $item['qbo_invoice_id']);

            if (!$invoice || !$invoice->qbo_id) {
                return (object) [
                    'status'   => 'queued',
                    'local_id' => $localPaymentId,
                    'reason'   => 'Linked invoice not yet synced to QBO',
                ];
            }
        }

        /**
         * 5️⃣ Build QBO payload
         */
        $payload = [
            'customer_qbo_id' => $customer->qbo_id,
            'amount'          => $data['total_amount'],
            'txn_date'        => $data['txn_date'] ?? null,
            'payment_ref'          => $data['payment_ref'] ?? null,
            'payment_method_qbo_id' => $data['payment_method_qbo_id'] ?? null,
            'bank_account'         => $data['deposit_account_id'] ?? null,
            'private_note'    => $data['private_note'] ?? null,
            'items'      => array_map(
                fn ($item) => [
                    'amount'     => $item['amount'],
                    'qbo_invoice_id' => $this->invoiceRepo
                        ->find((int) $item['qbo_invoice_id'])
                        ->qbo_id,
                ],
                $lineItems
            ),
        ];

        /**
         * 6️⃣ Attempt QBO sync
         */
        try {
            $qboPayment = $this->paymentClient->create($payload);

            $qboId = $qboPayment->Payment->Id ?? null;

            if ($qboId) {
                $this->paymentRepo->markSynced(
                    $localPaymentId,
                    $qboId,
                    $qboPayment->Payment->SyncToken ?? null
                );
            }

            return (object) [
                'status'   => 'synced',
                'local_id' => $localPaymentId,
                'qbo_id'   => $qboId,
            ];

        } catch (\Throwable $e) {
            error_log(
                "QBO Payment sync failed: " .
                $e->getMessage() .
                ":" .
                $e->getTraceAsString()
            );

            $this->paymentRepo->markFailed(
                $localPaymentId,
                $e->getMessage()
            );

            return (object) [
                'status'   => 'failed',
                'local_id' => $localPaymentId,
                'error'    => $e->getMessage(),
            ];
        }
    }

    public function update(array $data): object
    {
        $localId = $data['local_id'] ?? null;
        if (!$localId) {
            return (object) ['status' => 'error', 'error' => 'local_id is required'];
        }

        $existing = $this->paymentRepo->findByLocalId($localId);
        if (!$existing) {
            return (object) ['status' => 'error', 'error' => 'Payment not found'];
        }

        $this->paymentRepo->updatePayment((int) $existing->id, $data);

        try {
            $qboPayment = $this->paymentClient->getById($existing->qbo_id);
            $syncToken = $qboPayment->Payment->SyncToken ?? null;

            $qboResult = $this->paymentClient->update(
                $existing->qbo_id,
                $syncToken,
                $data
            );

            $newSyncToken = $qboResult->Payment->SyncToken ?? $syncToken;

            $this->paymentRepo->markSynced(
                (int) $existing->id,
                $existing->qbo_id,
                $newSyncToken
            );

            return (object) [
                'status'   => 'synced',
                'local_id' => $localId,
                'qbo_id'   => $existing->qbo_id,
            ];
        } catch (\Throwable $e) {
            error_log("QBO Payment update failed: " . $e->getMessage());
            return (object) [
                'status'   => 'failed',
                'local_id' => $localId,
                'error'    => $e->getMessage(),
            ];
        }
    }
}
