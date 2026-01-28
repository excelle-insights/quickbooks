<?php

/**
 * Retries pending/failed QBO payments using exponential backoff.
 *
 * Each failure increases `retry_count`, delaying the next retry
 * (2^retry_count × 30s, capped at 5 minutes) to avoid rate limits.
 *
 * Rows are atomically locked by marking them as `processing`
 * to prevent duplicate syncs when multiple cron jobs run.
 */


require dirname(__DIR__, 4) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;
use ExcelleInsights\QuickBooks\Repositories\QboPaymentRepository;
use ExcelleInsights\QuickBooks\Repositories\QboPaymentItemRepository;

// Init
$qbo = new QuickBooksManager();
$pdo = $qbo->getPdo();

// Repos
$repo = new QboPaymentRepository($pdo);
$itemRepo = new QboPaymentItemRepository($pdo);

// Fetch eligible payments
$payments = $repo->getRetryBatch(50);

foreach ($payments as $payment) {

    // 🔒 Atomic claim
    if (!$repo->claimForProcessing((int) $payment['id'])) {
        continue; // another worker got it
    }

    try {
        $payment['items'] = $itemRepo->getByPaymentId($payment['id']);

        $result = $qbo->createPayment($payment);

        $qboId = $result->Payment->Id ?? null;
        $syncToken = $result->Payment->SyncToken ?? null;

        if ($qboId) {
            $repo->markSynced(
                (int) $payment['id'],
                $qboId,
                $syncToken
            );
        }

    } catch (\Throwable $e) {
        error_log("QBO payment sync failed: {$payment['id']} → {$e->getMessage()}");

        $repo->markFailed(
            (int) $payment['id'],
            $e->getMessage()
        );
    }
}

echo "Payment retry finished at " . date('Y-m-d H:i:s') . PHP_EOL;
