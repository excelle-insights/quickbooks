<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboPaymentItemRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Create one or multiple payment items
     */
    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO qbo_payment_items
            (payment_id, qbo_invoice_id, amount, created_at)
            VALUES
            (:payment_id, :qbo_invoice_id, :amount, NOW())
        ");

        // Accept either a single item or multiple items
        $items = isset($data[0]) ? $data : [$data];

        foreach ($items as $item) {
            $stmt->execute([
                ':payment_id' => $item['payment_id'],
                ':qbo_invoice_id' => $item['qbo_invoice_id'],
                ':amount' => $item['amount'],
            ]);
        }
    }

    public function getItemsByPaymentId(int $paymentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM qbo_payment_items WHERE payment_id = :payment_id
        ");
        $stmt->execute([':payment_id' => $paymentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
