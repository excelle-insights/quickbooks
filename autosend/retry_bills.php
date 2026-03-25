<?php

require dirname(__DIR__, 4) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;
use ExcelleInsights\QuickBooks\Repositories\QboBillRepository;
use ExcelleInsights\QuickBooks\Repositories\QboBillItemRepository;

$qbo = new QuickBooksManager();
$pdo = $qbo->getPdo();

$billRepo     = new QboBillRepository($pdo);
$billItemRepo = new QboBillItemRepository($pdo);

$pending = $billRepo->getPending(5);

foreach ($pending as $bill) {
    try {
        // Rebuild items from local DB
        $items = $billItemRepo->findByBillId((int) $bill['id']);

        $data = [
            'qbo_company_id' => $bill['qbo_company_id'],
            'qbo_vendor_id'  => $bill['qbo_vendor_id'],
            'txn_date'       => $bill['txn_date'] ?? null,
            'currency'       => $bill['currency'] ?? null,
            'items'          => array_map(fn($i) => [
                'account_qbo_id' => $i['account_qbo_id'],
                'amount'         => $i['amount'],
                'description'    => $i['description'] ?? null,
                'qbo_class_id'   => $i['qbo_class_id'] ?? null,
            ], $items),
        ];

        $result = $qbo->createBill($data);

        if ($result->status === 'synced') {
            echo "Bill {$bill['id']} synced → QBO ID {$result->qbo_id}\n";
        } else {
            echo "Bill {$bill['id']} not synced: {$result->reason}\n";
        }

    } catch (\Throwable $e) {
        $billRepo->markFailed((int) $bill['id'], $e->getMessage());
        echo "Bill {$bill['id']} failed: {$e->getMessage()}\n";
    }
}

echo "Bill retry finished at " . date('Y-m-d H:i:s') . PHP_EOL;
