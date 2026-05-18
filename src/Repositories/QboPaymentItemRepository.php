<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboPaymentItemRepository
{
    private string $table;

    public function __construct(private PDO $pdo)
    {
        $this->table = ($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo') . '_payment_items';
    }

    /**
     * Create one or multiple payment items
     */
    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table}
            (qbo_payment_id, qbo_invoice_id, amount, created_at)
            VALUES
            (:qbo_payment_id, :qbo_invoice_id, :amount, NOW())
        ");

        // Accept either a single item or multiple items
        $items = isset($data[0]) ? $data : [$data];

        foreach ($items as $item) {
            $stmt->execute([
                ':qbo_payment_id' => $item['qbo_payment_id'],
                ':qbo_invoice_id' => $item['qbo_invoice_id'],
                ':amount' => $item['amount'],
            ]);
        }
    }

    public function getByPaymentId(int $paymentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->table} WHERE qbo_payment_id = :qbo_payment_id
        ");
        $stmt->execute([':qbo_payment_id' => $paymentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
