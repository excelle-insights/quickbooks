<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

// Initialize the manager
$qbo = new QuickBooksManager();

$index = 13;

$result = $qbo->createAccount([
    'qbo_company_id'   => 1,
    'name'             => 'Test Income Account ' . $index,
    'account_type'     => 'Income',
    'account_sub_type' => 'SalesOfProductIncome',
    'classification'   => 'Revenue',
    'description'      => 'Test income account created from SDK',
    'active'           => true,
]);

if ($result->status === 'synced') {
    echo "Account synced with QBO ID: " . $result->qbo_id;
} else {
    echo "Account queued for retry. Local ID: " . $result->local_id;
}
