<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

// Initialize the manager
$qbo = new QuickBooksManager();

$index = 1;

$data = [
    'qbo_company_id' => 1,
    'lines' => [
        [
            'description' => 'Test JE Line Item 1',
            'debit' => 100.00,
            'account_qbo_id' => 47, // Replace with a valid QBO account ID
        ],
        [
            'description' => 'Test JE Line Item 2',
            'credit' => 100.00,
            'account_qbo_id' => 64, // Replace with a valid QBO account ID
        ],
    ],
    'txn_date' => date('Y-m-d'),
    'doc_number' => 'JE00' . $index,
];
$result = $qbo->createJournalEntry($data);

if ($result->status === 'synced') {
    echo "Journal Entry synced with QBO ID: " . $result->qbo_id;
} else {
    echo "Journal Entry queued for retry. Local ID: " . $result->local_id;
}
