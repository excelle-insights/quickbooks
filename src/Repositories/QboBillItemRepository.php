<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboBillItemRepository
{
    private string $table;

    public function __construct(private PDO $pdo)
    {
        $this->table = ($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo') . '_bill_items';
    }

    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (
                bill_id,
                qbo_class_id,
                tax_code_id,
                account_qbo_id,
                amount,
                description,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $data['bill_id'],
            $data['qbo_class_id'] ?? null,
            $data['tax_code_id']  ?? null,
            $data['account_qbo_id'],
            $data['amount'],
            // Truncate to 4000 chars as a safety net (TEXT supports ~65k but QBO line descriptions are short)
            isset($data['description']) ? mb_substr($data['description'], 0, 4000) : null,
        ]);
    }

    /**
     * Insert multiple bill items for a local bill
     */
    public function createMany(int $billId, array $items): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (
                bill_id,
                qbo_class_id,
                tax_code_id,
                account_qbo_id,
                amount,
                description,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        foreach ($items as $item) {
            $stmt->execute([
                $billId,
                $item['qbo_class_id'] ?? null,
                $item['tax_code_id']  ?? null,
                $item['account_qbo_id'],
                $item['amount'],
                isset($item['description']) ? mb_substr($item['description'], 0, 4000) : null,
            ]);
        }
    }

    /**
     * Find items by bill ID
     */
    public function findByBillId(int $billId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE bill_id = ?");
        $stmt->execute([$billId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete all items for a bill (used on update)
     */
    public function deleteByBillId(int $billId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE bill_id = ?");
        $stmt->execute([$billId]);
    }
}
