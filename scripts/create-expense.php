<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

$qbo = new QuickBooksManager();

/**
 * Direct Procurement Expense
 *
 * Required:
 *   - qbo_company_id
 *   - payment_account_qbo_id  → QBO bank/credit account ID (Payment account on the form)
 *   - items[].account_qbo_id  → QBO expense account (Category column)
 *   - items[].amount
 *
 * Optional:
 *   - qbo_vendor_id           → local qbo_vendors.id (Payee / "Who did you pay?")
 *   - payment_type            → Cash | Check | CreditCard  (default: Cash)
 *   - payment_method          → free-text label shown in Payment Method field
 *   - txn_date                → Y-m-d  (Payment Date)
 *   - ref_number              → Ref no.
 *   - currency
 *   - memo
 *   - items[].description
 *   - items[].qbo_class_id    → local qbo_classes.id
 *   - items[].tax_code_id     → local qbo_tax_codes.id  (run syncTaxCodes() first)
 */

$result = $qbo->createExpense([
    'qbo_company_id'          => 1,
    'qbo_vendor_id'           => 3,          // local vendor ID (Payee)
    'payment_account_qbo_id'  => '35',       // QBO bank account ID
    'payment_type'            => 'Cash',
    'payment_method'          => 'Bank Transfer',
    'txn_date'                => date('Y-m-d'),
    'ref_number'              => 'EXP-001',
    'currency'                => 'KES',
    'memo'                    => 'Direct procurement — office supplies',
    'items'                   => [
        [
            'account_qbo_id' => '7',
            'amount'         => 5000.00,
            'description'    => 'Office supplies',
            'tax_code_id'    => 1,    // local qbo_tax_codes.id e.g. VAT 16%
            'qbo_class_id'   => null, // optional
        ],
        [
            'account_qbo_id' => '8',
            'amount'         => 2000.00,
            'description'    => 'Transport',
            'tax_code_id'    => 2,    // e.g. EXEMPT
            'qbo_class_id'   => null,
        ],
    ],
]);

if ($result->status === 'synced') {
    echo "Expense synced → QBO ID: {$result->qbo_id}" . PHP_EOL;
} elseif ($result->status === 'queued') {
    echo "Expense queued (vendor not yet synced). Local ID: {$result->local_id}" . PHP_EOL;
} else {
    echo "Expense failed. Local ID: {$result->local_id}" . PHP_EOL;
    echo "Error: " . ($result->error ?? 'Unknown') . PHP_EOL;
}
