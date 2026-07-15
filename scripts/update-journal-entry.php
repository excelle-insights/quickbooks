<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

$qbo = new QuickBooksManager();

// ── Step 1: Create a journal entry ──
$data = [
    'qbo_company_id' => 1,
    'local_id'       => time(), // unique source ID
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
    'doc_number' => 'JE-UPDATE-TEST',
];

$created = $qbo->createJournalEntry($data);

if ($created->status !== 'synced') {
    echo "Create failed: " . ($created->error ?? $created->reason ?? 'unknown') . "\n";
    exit(1);
}

echo "Created JE [{$created->source_local_id}] -> QBO ID: {$created->qbo_id}\n";

// ── Step 2: Sparse update (header only) ──
$updateHeader = [
    'qbo_company_id' => 1,
    'local_id'       => $created->source_local_id,
    'txn_date'       => date('Y-m-d', strtotime('+1 day')),
    'doc_number'     => 'JE-UPDATE-TEST-MODIFIED',
    'notes'          => 'Updated private note',
];

$updated = $qbo->updateJournalEntry($updateHeader);

if ($updated->status === 'synced') {
    echo "Header update synced: QBO ID {$updated->qbo_id}\n";
} else {
    echo "Header update failed: {$updated->error}\n";
}

// ── Step 3: Full update (header + lines replaced) ──
$updateFull = [
    'qbo_company_id' => 1,
    'local_id'       => $created->source_local_id,
    'txn_date'       => date('Y-m-d'),
    'doc_number'     => 'JE-UPDATE-TEST-FULL',
    'notes'          => 'Full update with new lines',
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

$updatedFull = $qbo->updateJournalEntry($updateFull);

if ($updatedFull->status === 'synced') {
    echo "Full update synced: QBO ID {$updatedFull->qbo_id}\n";
} else {
    echo "Full update failed: {$updatedFull->error}\n";
}

echo "\nDone.\n";
