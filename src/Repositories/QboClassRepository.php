<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboClassRepository
{
    private string $table;

    public function __construct(private PDO $pdo)
    {
        $this->table = ($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo') . '_classes';
    }

    /**
     * Create local class (pre-sync)
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (
                qbo_company_id,
                name,
                parent_id,
                active,
                status,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, 'pending', NOW(), NOW()
            )
        ");

        $stmt->execute([
            $data['qbo_company_id'],
            $data['name'],
            $data['parent_id'] ?? null,
            $data['active'] ?? true,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Mark class as synced
     */
    public function markSynced(
        int $id,
        string $qboId,
        string $syncToken
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->table}
            SET
                qbo_id = ?,
                sync_token = ?,
                status = 'synced',
                error_message = NULL,
                last_attempt_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$qboId, $syncToken, $id]);
    }

    /**
     * Mark sync failure
     */
    public function markFailed(int $id, string $error): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->table}
            SET
                status = 'failed',
                retry_count = retry_count + 1,
                error_message = ?,
                last_attempt_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$error, $id]);
    }

    /**
     * Find class by local ID
     */
    public function find(int $id): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE id = ?"
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Find class by QBO ID
     */
    public function findByQboId(string $qboId): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE qbo_id = ?"
        );
        $stmt->execute([$qboId]);

        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Get parent class (local reference)
     */
    public function getParent(int $parentId): ?object
    {
        return $this->find($parentId);
    }

    /**
     * Classes pending sync or retry
     */
    public function getPending(int $maxRetries = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM {$this->table}
            WHERE status IN ('pending', 'failed')
              AND retry_count < :maxRetries
            ORDER BY last_attempt_at ASC
        ");

        $stmt->execute([':maxRetries' => $maxRetries]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
