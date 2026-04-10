<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

$qbo = new QuickBooksManager();

/**
 * IMPORTANT:
 * - qbo_vendor_id  → local ID from qbo_vendors table (NOT the QBO ID)
 * - account_qbo_id → QBO expense account ID
 * - tax_code_id    → local ID from qbo_tax_codes (run syncTaxCodes() first)
 * - qbo_class_id   → local ID from qbo_classes (optional)
 *
 * First-time setup: run syncTaxCodes() once to pull VAT/tax codes from QBO.
 */

// Uncomment to sync tax codes on first run:
// $taxSync = $qbo->syncTaxCodes(1);
// print_r($taxSync);

$result = $qbo->createBill([
    'qbo_company_id' => 1,
    'qbo_vendor_id'  => 3,
    'txn_date'       => date('Y-m-d'),
    'currency'       => 'KES',
    'items'          => [
        [
            'account_qbo_id' => '7',
            'amount'         => 200.00,
            'description'    => 'Office supplies',
            'tax_code_id'    => 1,     // local qbo_tax_codes.id e.g. VAT 16%
            'qbo_class_id'   => null,  // optional
        ],
        [
            'account_qbo_id' => '7',
            'amount'         => 150.00,
            'description'    => 'Transport expense',
            'tax_code_id'    => 2,     // e.g. EXEMPT
            'qbo_class_id'   => null,
        ],
    ],
]);

if ($result->status === 'synced') {
    echo "Bill synced → QBO ID: {$result->qbo_id}" . PHP_EOL;
} elseif ($result->status === 'queued') {
    echo "Bill queued (vendor not yet synced). Local ID: {$result->local_id}" . PHP_EOL;
} else {
    echo "Bill failed. Local ID: {$result->local_id}" . PHP_EOL;
    echo "Error: " . ($result->error ?? 'Unknown') . PHP_EOL;
}
