<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboInvoiceRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Create a local invoice (before QBO sync)
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO qbo_invoices (
                qbo_company_id,
                customer_qbo_id,
                invoice_number,
                status,
                txn_date,
                due_date,
                currency,
                created_at,
                updated_at
            ) VALUES (
                :company_id,
                :customer_qbo_id,
                :invoice_number,
                :status,
                :txn_date,
                :due_date,
                :currency,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            ':company_id'       => $data['qbo_company_id'],
            ':customer_qbo_id'  => $data['customer_qbo_id'],
            ':invoice_number'   => $data['invoice_number'] ?? null,
            ':status'           => $data['status'] ?? 'draft',
            ':txn_date'         => $data['txn_date'] ?? date('Y-m-d'),
            ':due_date'         => $data['due_date'] ?? null,
            ':currency'         => $data['currency'] ?? 'KES',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Mark invoice as synced after successful QBO creation
     */
    public function markSynced(
        int $id,
        string $qboId,
        string $syncToken,
        float $total
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE qbo_invoices
            SET
                qbo_id      = :qbo_id,
                sync_token  = :sync_token,
                total       = :total,
                status      = 'synced',
                updated_at  = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':qbo_id'     => $qboId,
            ':sync_token' => $syncToken,
            ':total'      => $total,
            ':id'         => $id,
        ]);
    }

    /**
     * Mark invoice as failed (retry later)
     */
    public function markFailed(int $id, string $reason): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE qbo_invoices
            SET
                status = 'failed',
                error_message = :reason,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':reason' => $reason,
            ':id'     => $id,
        ]);
    }

    /**
     * Find by local ID
     */
    public function find(int $id): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM qbo_invoices WHERE id = ?"
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Find by QBO ID (webhooks)
     */
    public function findByQboId(string $qboId): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM qbo_invoices WHERE qbo_id = ?"
        );
        $stmt->execute([$qboId]);

        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Invoices pending sync
     */
    public function unsynced(int $companyId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM qbo_invoices
            WHERE qbo_company_id = ?
              AND qbo_id IS NULL
              AND status IN ('draft', 'failed')
            ORDER BY created_at ASC
        ");

        $stmt->execute([$companyId]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Update local invoice
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE qbo_invoices
            SET
                invoice_number = :invoice_number,
                txn_date       = :txn_date,
                due_date       = :due_date,
                currency       = :currency,
                updated_at     = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':invoice_number' => $data['invoice_number'] ?? null,
            ':txn_date'       => $data['txn_date'],
            ':due_date'       => $data['due_date'] ?? null,
            ':currency'       => $data['currency'] ?? 'KES',
            ':id'             => $id,
        ]);
    }
}
