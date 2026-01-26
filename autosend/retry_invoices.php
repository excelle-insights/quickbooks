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
// Retry Invoices
// ----------------------
$invoiceRepo = new QboInvoiceRepository($pdo);

$pendingInvoices = $invoiceRepo->getPending(5);

foreach ($pendingInvoices as $invoice) {
    try {
        $qboInvoice = $qbo->createInvoice(json_decode($invoice['payload'], true));
        
        // mark as synced
        $invoiceRepo->markSynced($invoice['id'], $qboInvoice->Id, $qboInvoice->Invoice->SyncToken, $qboInvoice->Invoice->TotalAmt);
    } catch (\Throwable $e) {
        $invoiceRepo->markFailed($invoice['id'], $e->getMessage());
    }
}

echo "Retry finished at " . date('Y-m-d H:i:s') . PHP_EOL;
