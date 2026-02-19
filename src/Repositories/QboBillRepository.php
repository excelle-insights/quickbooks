<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboBillRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Insert a local bill (before QBO sync)
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO qbo_bills (
                qbo_company_id,
                qbo_vendor_id,
                txn_date,
                currency,
                status,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())
        ");

        $stmt->execute([
            $data['qbo_company_id'],
            $data['qbo_vendor_id'],
            $data['txn_date'] ?? null,
            $data['currency'] ?? null
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Mark a bill as synced
     */
    public function markSynced(int $id, string $qboId, string $syncToken): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE qbo_bills
            SET qbo_id = ?, sync_token = ?, status = 'synced', last_attempt_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$qboId, $syncToken, $id]);
    }

    /**
     * Mark a bill as failed
     */
    public function markFailed(int $id, string $error): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE qbo_bills
            SET status = 'failed',
                retry_count = retry_count + 1,
                last_attempt_at = NOW(),
                error_message = :error
            WHERE id = :id
        ");

        $stmt->execute([':error' => $error, ':id' => $id]);
    }

    /**
     * Find bill by local ID
     */
    public function find(int $id): ?object
    {
        $stmt = $this->pdo->prepare("SELECT * FROM qbo_bills WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Find bill by QBO ID
     */
    public function findByQboId(string $qboId): ?object
    {
        $stmt = $this->pdo->prepare("SELECT * FROM qbo_bills WHERE qbo_id = ?");
        $stmt->execute([$qboId]);

        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Get pending bills for initial sync or retry
     */
    public function getPending(int $maxRetries = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM qbo_bills
            WHERE status IN ('pending','failed')
              AND retry_count < :maxRetries
            ORDER BY last_attempt_at ASC
        ");

        $stmt->execute([':maxRetries' => $maxRetries]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
