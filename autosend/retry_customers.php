<?php

require dirname(__DIR__, 4) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;
use ExcelleInsights\QuickBooks\Repositories\QboCustomerRepository;
use ExcelleInsights\QuickBooks\Repositories\QboInvoiceRepository;

// 1. Initialize manager
$qbo = new QuickBooksManager();

// 2. Connect to database
$pdo = $qbo->getPdo(); // add getter if needed

// ----------------------
// Retry Customers
// ----------------------
$customerRepo = new QboCustomerRepository($pdo);

// pick pending or failed customers, e.g., last 5 retries max
$pendingCustomers = $customerRepo->getPending(5);

foreach ($pendingCustomers as $customer) {
    try {
        $qboCustomer = $qbo->createCustomer(json_decode($customer['payload'], true));
        
        // Mark as synced
        $customerRepo->markSynced($customer['id'], $qboCustomer->Customer->Id, $qboCustomer->Customer->SyncToken);
    } catch (\Throwable $e) {
        $customerRepo->markFailed($customer['id'], $e->getMessage());
    }
}

echo "Retry finished at " . date('Y-m-d H:i:s') . PHP_EOL;
