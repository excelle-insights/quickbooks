<?php

namespace ExcelleInsights\QuickBooks\Services;

use ExcelleInsights\QuickBooks\Client\PaymentClient;
use ExcelleInsights\QuickBooks\Repositories\QboPaymentRepository;
use ExcelleInsights\QuickBooks\Repositories\QboPaymentItemRepository;
use DateTime;

class PaymentSyncService
{
    private PaymentClient $paymentClient;
    private QboPaymentRepository $repository;
    private QboPaymentItemRepository $paymentItemRepository;
    private int $maxRetries = 3;

    public function __construct(PaymentClient $paymentClient, QboPaymentRepository $repository, QboPaymentItemRepository $paymentItemRepository)
    {
        $this->paymentClient = $paymentClient;
        $this->repository = $repository;
        $this->paymentItemRepository = $paymentItemRepository;
    }

    /**
     * Create a new payment in QuickBooks and update local DB
     */
    public function create(array $data): object
    {
        if (empty($data['customer_qbo_id'])) {
            throw new \InvalidArgumentException('customer_qbo_id is required');
        }

        if (empty($data['line_items']) || !is_array($data['line_items'])) {
            throw new \InvalidArgumentException('line_items are required');
        }


        // Send payment to QuickBooks
        $response = $this->paymentClient->create($data);

        $qboId = $response->Payment->Id ?? null;
        
        if ($qboId) {
            // Update local DB as synced
            $this->repository->markSynced(
                $data['id'],
                $qboId,
                $response->Payment->SyncToken ?? null
            );
        }


        return $response;
    }
    /**
     * Sync all pending or failed payments
     */
    public function syncPendingPayments(): void
    {
        $payments = $this->repository->getPendingOrFailed();

        foreach ($payments as $payment) {
            try {
                $payload = [
                    'customer_qbo_id' => $payment['qbo_customer_id'],
                    'amount' => $payment['total_amount'],
                    'txn_date' => $payment['txn_date'],
                    'transaction_ref' => $payment['payment_ref'],
                    'bank_account' => $payment['deposit_account_id'],
                    'private_note' => $payment['private_note'] ?? null,
                    'line_items' => $this->paymentItemRepository->getItemsByPaymentId($payment['id'])
                ];

                $response = $this->paymentClient->create($payload);

                // Update local record as synced
                $this->repository->markSynced(
                    $payment['id'],
                    $response->Payment->Id ?? null,
                    $response->Payment->SyncToken ?? null
                );
            } catch (\Throwable $e) {
                $this->repository->markFailed(
                    $payment['id'],
                    $e->getMessage()
                );
            }
        }
    }
}
