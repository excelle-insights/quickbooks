<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

$qbo = new QuickBooksManager();

$data = [
    'qbo_company_id' => 1,
    'local_id'       => time(),
    'lines' => [
        [
            'description' => 'Initial debit line',
            'debit'        => 200.00,
            'account_qbo_id' => 47,
        ],
        [
            'description' => 'Initial credit line',
            'credit'       => 200.00,
            'account_qbo_id' => 64,
        ],
    ],
    'txn_date'   => date('Y-m-d'),
    'doc_number' => 'JE-FULL-TEST',
];

$created = $qbo->createJournalEntry($data);

if ($created->status !== 'synced') {
    echo "Create failed: " . ($created->error ?? $created->reason ?? 'unknown') . "\n";
    exit(1);
}

echo "Created JE [{$created->source_local_id}] -> QBO ID: {$created->qbo_id}\n";

$updateFull = [
    'qbo_company_id' => 1,
    'local_id'       => $created->source_local_id,
    'txn_date'       => date('Y-m-d'),
    'doc_number'     => 'JE-FULL-TEST-MODIFIED',
    'notes'          => 'Full update — header and lines replaced',
    'lines' => [
        [
            'description' => 'Replaced debit line',
            'debit'        => 150.00,
            'account_qbo_id' => 47,
        ],
        [
            'description' => 'Replaced credit line',
            'credit'       => 150.00,
            'account_qbo_id' => 64,
        ],
    ],
];

$updated = $qbo->updateJournalEntry($updateFull);

if ($updated->status === 'synced') {
    echo "Full update synced: QBO ID {$updated->qbo_id}\n";
} else {
    echo "Full update failed: {$updated->error}\n";
}
