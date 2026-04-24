<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboExpenseRepository
{
    public function __construct(private PDO $pdo) {}

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO qbo_expenses (
                qbo_company_id,
                qbo_vendor_id,
                payment_account_qbo_id,
                payment_method,
                payment_type,
                txn_date,
                ref_number,
                currency,
                memo,
                status,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");

        $stmt->execute([
            $data['qbo_company_id'],
            $data['qbo_vendor_id']          ?? null,
            $data['payment_account_qbo_id'],
            $data['payment_method']         ?? null,
            $data['payment_type']           ?? 'Cash',
            $data['txn_date']               ?? null,
            $data['ref_number']             ?? null,
            $data['currency']               ?? null,
            $data['memo']                   ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function markSynced(int $id, string $qboId, string $syncToken): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE qbo_expenses
            SET qbo_id = ?, sync_token = ?, status = 'synced', last_attempt_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$qboId, $syncToken, $id]);
    }

    public function markFailed(int $id, string $error): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE qbo_expenses
            SET status = 'failed',
                retry_count = retry_count + 1,
                last_attempt_at = NOW(),
                error_message = :error
            WHERE id = :id
        ");
        $stmt->execute([':error' => $error, ':id' => $id]);
    }

    public function find(int $id): ?object
    {
        $stmt = $this->pdo->prepare("SELECT * FROM qbo_expenses WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function findByQboId(string $qboId): ?object
    {
        $stmt = $this->pdo->prepare("SELECT * FROM qbo_expenses WHERE qbo_id = ?");
        $stmt->execute([$qboId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function getPending(int $maxRetries = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM qbo_expenses
            WHERE status IN ('pending', 'failed')
              AND retry_count < :maxRetries
            ORDER BY last_attempt_at ASC
        ");
        $stmt->execute([':maxRetries' => $maxRetries]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
