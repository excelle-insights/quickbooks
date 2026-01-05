<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboCustomerRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Insert local customer (before QBO sync)
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO qbo_customers (
                qbo_company_id,
                display_name,
                email,
                phone,
                active,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $data['qbo_company_id'],
            $data['display_name'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
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
            UPDATE qbo_customers
            SET qbo_id = ?, sync_token = ?, updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$qboId, $syncToken, $id]);
    }

    /**
     * Update local customer (local source of truth)
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE qbo_customers
            SET
                display_name = ?,
                email = ?,
                phone = ?,
                active = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $data['display_name'],
            $data['email'] ?? null,
            $data['phone'] ?? null,
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
            "SELECT * FROM qbo_customers WHERE id = ?"
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
            "SELECT * FROM qbo_customers WHERE qbo_id = ?"
        );
        $stmt->execute([$qboId]);

        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Customers pending initial sync
     */
    public function unsynced(int $companyId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM qbo_customers
            WHERE qbo_company_id = ?
              AND qbo_id IS NULL
        ");
        $stmt->execute([$companyId]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
