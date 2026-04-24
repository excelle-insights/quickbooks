<?php

require dirname(__DIR__, 4) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;
use ExcelleInsights\QuickBooks\Repositories\QboExpenseRepository;
use ExcelleInsights\QuickBooks\Repositories\QboExpenseItemRepository;

$qbo = new QuickBooksManager();
$pdo = $qbo->getPdo();

$expenseRepo     = new QboExpenseRepository($pdo);
$expenseItemRepo = new QboExpenseItemRepository($pdo);

$pending = $expenseRepo->getPending(5);

foreach ($pending as $expense) {
    try {
        $items = $expenseItemRepo->findByExpenseId((int) $expense['id']);

        $data = [
            'qbo_company_id'         => $expense['qbo_company_id'],
            'qbo_vendor_id'          => $expense['qbo_vendor_id']          ?? null,
            'payment_account_qbo_id' => $expense['payment_account_qbo_id'],
            'payment_type'           => $expense['payment_type']           ?? 'Cash',
            'payment_method'         => $expense['payment_method']         ?? null,
            'txn_date'               => $expense['txn_date']               ?? null,
            'ref_number'             => $expense['ref_number']             ?? null,
            'currency'               => $expense['currency']               ?? null,
            'memo'                   => $expense['memo']                   ?? null,
            'items'                  => array_map(fn($i) => [
                'account_qbo_id' => $i['account_qbo_id'],
                'amount'         => $i['amount'],
                'description'    => $i['description'] ?? null,
                'qbo_class_id'   => $i['qbo_class_id'] ?? null,
                'tax_code_id'    => $i['tax_code_id']  ?? null,
            ], $items),
        ];

        $result = $qbo->createExpense($data);

        if ($result->status === 'synced') {
            echo "Expense {$expense['id']} synced → QBO ID {$result->qbo_id}\n";
        } else {
            echo "Expense {$expense['id']} not synced: " . ($result->reason ?? $result->error ?? 'unknown') . "\n";
        }

    } catch (\Throwable $e) {
        $expenseRepo->markFailed((int) $expense['id'], $e->getMessage());
        echo "Expense {$expense['id']} failed: {$e->getMessage()}\n";
    }
}

echo "Expense retry finished at " . date('Y-m-d H:i:s') . PHP_EOL;
