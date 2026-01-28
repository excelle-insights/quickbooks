<?php

require dirname(__DIR__, 4) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;
use ExcelleInsights\QuickBooks\Repositories\QboPaymentRepository;
use ExcelleInsights\QuickBooks\Repositories\QboPaymentItemRepository;

// 1. Initialize manager
$qbo = new QuickBooksManager();

// 2. Connect to database
$pdo = $qbo->getPdo(); // add getter if needed

// ----------------------
// Retry Payments
// ----------------------
$repo = new QboPaymentRepository($pdo);
$paymentItemRepo = new QboPaymentItemRepository($pdo);

$pendingPayments = $repo->getUnsynced();

foreach ($pendingPayments as $payment) {
    // Add items
    $payment['items'] = $paymentItemRepo->getByPaymentId($payment['id']);

    try {
        $qboPayment = $qbo->createPayment($payment);
        
        $qboId = $qboPayment->Payment->Id ?? null;

        if ($qboId) {
            // mark as synced
            $repo->markSynced($payment['id'], $qboId, $qboPayment->Payment->SyncToken);
        }

    } catch (\Throwable $e) {
        error_log(json_encode($payment));
        $repo->markFailed($payment['id'], $e->getMessage());
    }
}

echo "Retry finished at " . date('Y-m-d H:i:s') . PHP_EOL;
