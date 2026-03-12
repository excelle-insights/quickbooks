<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboInvoiceItemRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Insert a local invoice line item
     */
    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO qbo_invoice_items (
                qbo_invoice_id,
                qbo_class_id,
                item_id,
                item_name,
                description,
                quantity,
                unit_price,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $data['qbo_invoice_id'] ?? null,
            $data['qbo_class_id'] ?? null,
            $data['item_id'] ?? null,
            $data['item_name'] ?? null,
            $data['description'] ?? '',
            $data['quantity'] ?? 1,
            $data['unit_price'] ?? 0,
        ]);
    }

    /**
     * Get invoice items formatted for QuickBooks API
     */
    public function forInvoice(int $invoiceId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT description, quantity, unit_price, amount
            FROM qbo_invoice_items
            WHERE qbo_invoice_id = ?
        ");
        $stmt->execute([$invoiceId]);

        $items = [];

        foreach ($stmt->fetchAll(PDO::FETCH_OBJ) as $row) {
            $items[] = [
                'DetailType' => 'SalesItemLineDetail',
                'Amount' => (float) $row->amount,
                'Description' => $row->description,
                'SalesItemLineDetail' => [
                    "ItemRef" => [
                        "value" => isset($row->item_id) ? $row->item_id : "",
                        "name" => isset($row->item_name) ? $row->item_name : ""
                    ],
                    'Qty' => (float) $row->quantity,
                    'UnitPrice' => (float) $row->unit_price,
                ],
            ];
        }

        return $items;
    }
}
