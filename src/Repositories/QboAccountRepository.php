<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboAccountRepository
{
    private string $table;

    public function __construct(private PDO $pdo)
    {
        $this->table = ($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo') . '_accounts';
    }

    /**
     * Insert local account (before QBO sync)
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (
                qbo_company_id,
                name,
                account_type,
                account_sub_type,
                classification,
                description,
                sub_account,
                parent_qbo_id,
                currency,
                active,
                status,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW()
            )
        ");

        $stmt->execute([
            $data['qbo_company_id'],
            $data['name'],
            $data['account_type'],
            $data['account_sub_type'] ?? null,
            $data['classification'] ?? null,
            $data['description'] ?? null,
            $data['sub_account'] ?? null,
            $data['parent_qbo_id'] ?? null,
            $data['currency'] ?? null,
            $data['active'] ?? true,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Attach QBO identifiers after successful sync
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
                last_attempt_at = NOW()
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
                last_attempt_at = NOW(),
                error_message = :error
            WHERE id = :id
        ");

        $stmt->execute([
            ':error' => $error,
            ':id'    => $id,
        ]);
    }

    /**
     * Update local account (local source of truth)
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->table}
            SET
                name = ?,
                account_type = ?,
                account_sub_type = ?,
                classification = ?,
                description = ?,
                sub_account = ?,
                parent_qbo_id = ?,
                currency = ?,
                active = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $data['name'],
            $data['account_type'],
            $data['account_sub_type'] ?? null,
            $data['classification'] ?? null,
            $data['description'] ?? null,
            $data['sub_account'] ?? false,
            $data['parent_qbo_id'] ?? null,
            $data['currency'] ?? null,
            $data['active'] ?? true,
            $id,
        ]);
    }

    /**
     * Find by local ID
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
     * Find by QBO ID (used by webhooks)
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
     * Accounts pending initial sync or retry
     */
    public function getPending(int $maxRetries = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM {$this->table}
            WHERE status IN ('pending','failed')
              AND retry_count < :maxRetries
            ORDER BY last_attempt_at ASC
        ");

        $stmt->execute([':maxRetries' => $maxRetries]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
