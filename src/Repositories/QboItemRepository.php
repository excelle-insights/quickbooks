<?php

/**
 * File: src/Repositories/QboItemRepository.php
 *
 * Repository for the qbo_items table. Handles all local CRUD operations
 * for QuickBooks Online Items (Products/Services/Categories).
 * Follows the same patterns as QboBillRepository.
 * Table name is resolved from QBO_TABLE_PREFIX env variable.
 */

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboItemRepository
{
    private string $table;

    public function __construct(private PDO $pdo)
    {
        $prefix = $_ENV['QBO_TABLE_PREFIX'] ?? 'qbo';
        $this->table = $prefix . '_items';
    }

    /**
     * Insert a local item (before QBO sync)
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (
                qbo_company_id,
                name,
                type,
                description,
                active,
                sub_item,
                parent_ref,
                level,
                fully_qualified_name,
                taxable,
                unit_price,
                purchase_cost,
                income_account_qbo_id,
                expense_account_qbo_id,
                class_qbo_id,
                track_qty_on_hand,
                qty_on_hand,
                status,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");

        $stmt->execute([
            $data['qbo_company_id'],
            $data['name'],
            $data['type'] ?? 'Service',
            $data['description'] ?? null,
            isset($data['active']) ? (int) $data['active'] : 1,
            isset($data['sub_item']) ? (int) $data['sub_item'] : 0,
            $data['parent_ref'] ?? null,
            $data['level'] ?? 0,
            $data['fully_qualified_name'] ?? null,
            isset($data['taxable']) ? (int) $data['taxable'] : 0,
            $data['unit_price'] ?? 0,
            $data['purchase_cost'] ?? 0,
            $data['income_account_qbo_id'] ?? null,
            $data['expense_account_qbo_id'] ?? null,
            $data['class_qbo_id'] ?? null,
            isset($data['track_qty_on_hand']) ? (int) $data['track_qty_on_hand'] : 0,
            $data['qty_on_hand'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Mark an item as synced
     */
    public function markSynced(int $id, string $qboId, string $syncToken): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->table}
            SET qbo_id = ?, sync_token = ?, status = 'synced', last_attempt_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$qboId, $syncToken, $id]);
    }

    /**
     * Mark an item as failed
     */
    public function markFailed(int $id, string $error): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->table}
            SET status = 'failed',
                retry_count = retry_count + 1,
                last_attempt_at = NOW(),
                error_message = :error,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([':error' => $error, ':id' => $id]);
    }

    /**
     * Find item by local ID
     */
    public function find(int $id): ?object
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Find item by QBO ID
     */
    public function findByQboId(string $qboId): ?object
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE qbo_id = ?");
        $stmt->execute([$qboId]);

        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Find item by name and company
     */
    public function findByName(int $qboCompanyId, string $name): ?object
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->table}
            WHERE qbo_company_id = ? AND name = ?
            LIMIT 1
        ");
        $stmt->execute([$qboCompanyId, $name]);

        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Get all items for a company, optionally filtered by type
     */
    public function getAll(int $qboCompanyId, ?string $type = null): array
    {
        $sql    = "SELECT * FROM {$this->table} WHERE qbo_company_id = ?";
        $params = [$qboCompanyId];

        if ($type !== null) {
            $sql      .= " AND type = ?";
            $params[] = $type;
        }

        $sql .= " ORDER BY fully_qualified_name ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get sub-items of a parent (by parent's QBO ID)
     */
    public function getChildren(string $parentQboId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->table}
            WHERE parent_ref = ?
            ORDER BY name ASC
        ");
        $stmt->execute([$parentQboId]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Get pending items for initial sync or retry
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

    /**
     * Upsert an item from QBO pull (sync from QBO → local)
     * Uses qbo_id + qbo_company_id as the unique key
     */
    public function upsertFromQbo(int $qboCompanyId, array $data): int
    {
        $existing = $this->findByQboId($data['qbo_id']);

        if ($existing) {
            $stmt = $this->pdo->prepare("
                UPDATE {$this->table}
                SET name                   = ?,
                    type                   = ?,
                    description            = ?,
                    active                 = ?,
                    sub_item               = ?,
                    parent_ref             = ?,
                    level                  = ?,
                    fully_qualified_name   = ?,
                    taxable                = ?,
                    unit_price             = ?,
                    purchase_cost          = ?,
                    income_account_qbo_id  = ?,
                    expense_account_qbo_id = ?,
                    class_qbo_id           = ?,
                    track_qty_on_hand      = ?,
                    qty_on_hand            = ?,
                    sync_token             = ?,
                    status                 = 'synced',
                    error_message          = NULL,
                    updated_at             = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $data['name'],
                $data['type'],
                $data['description'] ?? null,
                (int) ($data['active'] ?? true),
                (int) ($data['sub_item'] ?? false),
                $data['parent_ref'] ?? null,
                $data['level'] ?? 0,
                $data['fully_qualified_name'] ?? null,
                (int) ($data['taxable'] ?? false),
                $data['unit_price'] ?? 0,
                $data['purchase_cost'] ?? 0,
                $data['income_account_qbo_id'] ?? null,
                $data['expense_account_qbo_id'] ?? null,
                $data['class_qbo_id'] ?? null,
                (int) ($data['track_qty_on_hand'] ?? false),
                $data['qty_on_hand'] ?? null,
                $data['sync_token'] ?? null,
                $existing->id,
            ]);

            return (int) $existing->id;
        }

        $data['qbo_company_id'] = $qboCompanyId;
        $localId = $this->create($data);

        // Immediately mark as synced since it came from QBO
        $this->markSynced($localId, $data['qbo_id'], $data['sync_token'] ?? '0');

        return $localId;
    }
}