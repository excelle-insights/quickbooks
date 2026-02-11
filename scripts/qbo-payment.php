<?php
require '../../vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

// Initialize the manager
$qbo = new QuickBooksManager();

// Create a payment
$result = $qbo->createPayment([
    'qbo_company_id'  => 1,
    'qbo_customer_id' => 4, // local customer ID (must already be synced to QBO)

    'pay_id'          => 1005, // local payment ID
    'total_amount'    => 5000,
    'txn_date'        => '2026-01-25',
    'payment_ref'     => 'MPESA-TRX-982344',
    'deposit_account_id' => '35', // QBO Bank account ID
    'private_note'    => 'Payment for January invoices',
    'items' => [
        [
            'qbo_invoice_id' => 4,   // local invoice ID (must already be synced)
            'amount'     => 5000,
        ],
    ],
]);

if ($result->status === 'synced') {
    echo "Payment synced with QBO ID: " . $result->qbo_id;
} else {
    echo "Payment queued for retry. Local ID: " . $result->local_id;

    if (!empty($result->error)) {
        echo "\nError: " . $result->error;
    }

    if (!empty($result->reason)) {
        echo "\nReason: " . $result->reason;
    }
}
