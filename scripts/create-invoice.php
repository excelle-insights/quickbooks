<?php
require '../../vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

// Initialize the manager
$qbo = new QuickBooksManager();

// Create an invoice
$result = $qbo->createInvoice([
    'qbo_company_id'  => 1,
    'qbo_customer_id' => 10, // Replace with actual QBO customer ID
    'invoice_number'  => 'INV-002',
    'txn_date'        => '2026-01-22',
    'due_date'        => '2026-02-05',
    'currency'        => 'KES',
    'items' => [
        [
            'description' => 'Consulting Services',
            'quantity'    => 1,
            'unit_price'  => 7000,
            'amount'      => 7000, // quantity * unit_price
        ]
    ],
]);

if ($result->status === 'synced') {
    echo "Invoice synced with QBO ID: " . $result->qbo_id;
} else {
    echo "Invoice queued for retry. Local ID: " . $result->local_id;
    if (!empty($result->error)) {
        echo "\nError: " . $result->error;
    }
}
