<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

// Initialize the manager
$qbo = new QuickBooksManager();

// Create an invoice
$result = $qbo->createInvoice([
    'qbo_company_id'  => 1,
    'qbo_customer_id' => 2, // Replace with actual QBO customer ID
    'invoice_number'  => 'INV-0045',
    'txn_date'        => '2026-02-22',
    'due_date'        => '2026-02-25',
    'currency'        => 'KES',
    'items' => [
        [
            'qbo_item_id' => 9, 
            'description' => 'Rent Deposit',
            'quantity'    => 1,
            'unit_price'  => 7500,
            'amount'      => 7500, 
            // 'qbo_class_id' => 1, // Optional: Replace with actual QBO class ID if needed
            'qbo_tax_id' => 1, // Optional: Replace with actual QBO tax code ID if needed
        ],
        [
            'qbo_item_id' => 9, 
            'description' => 'Rent for February 2026',
            'quantity'    => 1,
            'unit_price'  => 7500,
            'amount'      => 7500, 
            // 'qbo_class_id' => 1, // Optional: Replace with actual QBO class ID if needed
            'qbo_tax_id' => 3, // Optional: Replace with actual QBO tax code ID if needed
        ],
        [
            'qbo_item_id' => 12, 
            'description' => 'Service charge',
            'quantity'    => 1,
            'unit_price'  => 500,
            'amount'      => 500, 
            // 'qbo_class_id' => 2, // Optional: Replace with actual QBO class ID if needed
            'qbo_tax_id' => 3, // Optional: Replace with actual QBO tax code ID if needed

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
