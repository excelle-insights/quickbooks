<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboCustomerRepository
{
    private string $table;

    public function __construct(private PDO $pdo)
    {
        $this->table = ($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo') . '_customers';
    }

    /**
     * Insert local customer (before QBO sync)
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (
                local_id,
                qbo_company_id,
                parent_id,
                name,
                display_name,
                first_name,
                middle_name,
                last_name,
                email,
                phone,
                kra_pin,
                company_name,
                country,
                city,
                postal_code,
                line,
                line1,
                line2,
                active,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $data['local_id'],
            $data['qbo_company_id'],
            $data['parent_id'],
            $data['name'],
            $data['display_name'] ?? $data['name'],
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
            $data['last_name'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['kra_pin'] ?? null,
            $data['company_name'] ?? null,
            $data['country'] ?? null,
            $data['city'] ?? null,
            $data['postal_code'] ?? null,
            $data['line'] ?? null,
            $data['line1'] ?? null,
            $data['line2'] ?? null,
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
            SET qbo_id = ?, sync_token = ?, status = ?, last_attempt_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$qboId, $syncToken, 'synced', $id]);
    }
    public function markFailed(int $id, string $error): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->table}
            SET status = 'failed',
                retry_count = retry_count + 1,
                last_attempt_at = NOW(),
                error_message = :error
            WHERE id = :id
        ");
        $stmt->execute([':error' => $error, ':id' => $id]);
    }

    /**
     * Update local customer (local source of truth)
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->table}
            SET
                name = ?,
                display_name = ?,
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                email = ?,
                phone = ?,
                kra_pin = ?,
                company_name = ?,
                country = ?,
                city = ?,
                postal_code = ?,
                line = ?,
                line1 = ?,
                line2 = ?,
                active = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $data['name'],
            $data['display_name'] ?? $data['name'],
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
            $data['last_name'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['kra_pin'] ?? null,
            $data['company_name'] ?? null,
            $data['country'] ?? null,
            $data['city'] ?? null,
            $data['postal_code'] ?? null,
            $data['line'] ?? null,
            $data['line1'] ?? null,
            $data['line2'] ?? null,
            $data['active'] ?? true,
            $id
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
     * Customers pending initial sync
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
    /**
     * Get all synced customers (those with a valid qbo_id)
     */
    public function getAllSynced(int $qboCompanyId = 1): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->table}
            WHERE qbo_id IS NOT NULL AND status = 'synced' AND qbo_company_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$qboCompanyId]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
