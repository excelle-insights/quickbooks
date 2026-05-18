<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboExpenseItemRepository
{
    private string $table;

    public function __construct(private PDO $pdo)
    {
        $this->table = ($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo') . '_expense_items';
    }

    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (
                expense_id,
                account_qbo_id,
                qbo_class_id,
                tax_code_id,
                amount,
                description,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $data['expense_id'],
            $data['account_qbo_id'],
            $data['qbo_class_id'] ?? null,
            $data['tax_code_id']  ?? null,
            $data['amount'],
            $data['description']  ?? null,
        ]);
    }

    public function findByExpenseId(int $expenseId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE expense_id = ?");
        $stmt->execute([$expenseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteByExpenseId(int $expenseId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE expense_id = ?");
        $stmt->execute([$expenseId]);
    }
}
