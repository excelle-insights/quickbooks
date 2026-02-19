<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

// Initialize the manager
$qbo = new QuickBooksManager();

$index = 13;

/**
 * IMPORTANT:
 * Replace these with real QBO IDs from your system.
 */
$vendorQboId  = '56';   // Existing Vendor QBO ID
$accountQboId = '7';    // Existing Expense Account QBO ID

$result = $qbo->createBill([
    'qbo_company_id' => 1,
    'vendor_qbo_id'  => $vendorQboId,

    // Optional
    'txn_date' => date('Y-m-d'),
    'currency' => 'KES',

    'items' => [
        [
            'account_qbo_id' => $accountQboId,
            'amount'         => 200.00,
            'description'    => "Office supplies expense {$index}",
        ],
        [
            'account_qbo_id' => $accountQboId,
            'amount'         => 150.00,
            'description'    => "Transport expense {$index}",
        ],
    ],
]);

if ($result->status === 'synced') {
    echo "Bill synced with QBO ID: " . $result->qbo_id . PHP_EOL;
} else {
    echo "Bill queued for retry. Local ID: " . $result->local_id . PHP_EOL;
    echo "Error: " . ($result->error ?? 'Unknown error') . PHP_EOL;
}
