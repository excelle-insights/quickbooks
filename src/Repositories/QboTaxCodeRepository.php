<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboTaxCodeRepository
{
    public function __construct(private PDO $pdo) {}

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
    /**
     * Upsert a tax code synced from QBO.
     * QBO is the source of truth — we never create tax codes locally.
     */
    public function upsert(array $data): int
    {
        // Check if already exists by qbo_id
        $existing = $this->findByQboId($data['qbo_id']);

        if ($existing) {
            $stmt = $this->pdo->prepare("
                UPDATE qbo_tax_codes SET
                    name            = ?,
                    description     = ?,
                    taxable         = ?,
                    active          = ?,
                    sync_token      = ?,
                    status          = 'synced',
                    error_message   = NULL,
                    last_attempt_at = NOW(),
                    updated_at      = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['taxable'] ?? true,
                $data['active'] ?? true,
                $data['sync_token'] ?? null,
                $existing->id,
            ]);
            return (int) $existing->id;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO qbo_tax_codes (
                qbo_company_id, qbo_id, name, description,
                taxable, active, sync_token, status,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'synced', NOW(), NOW())
        ");
        $stmt->execute([
            $data['qbo_company_id'],
            $data['qbo_id'],
            $data['name'],
            $data['description'] ?? null,
            $data['taxable'] ?? true,
            $data['active'] ?? true,
            $data['sync_token'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function find(int $id): ?object
    {
        $stmt = $this->pdo->prepare("SELECT * FROM qbo_tax_codes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function findByQboId(string $qboId): ?object
    {
        $stmt = $this->pdo->prepare("SELECT * FROM qbo_tax_codes WHERE qbo_id = ?");
        $stmt->execute([$qboId]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Get all active tax codes for a company (for UI dropdowns etc.)
     */
    public function getAllActive(int $qboCompanyId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM qbo_tax_codes
            WHERE qbo_company_id = ? AND active = 1 AND status = 'synced'
            ORDER BY name ASC
        ");
        $stmt->execute([$qboCompanyId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
