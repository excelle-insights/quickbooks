<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboVendorRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Create local vendor (pre-sync)
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO qbo_vendors (
                qbo_company_id,
                display_name,
                given_name,
                family_name,
                title,
                suffix,
                company_name,
                print_on_check_name,
                email,
                phone,
                mobile,
                website,
                tax_identifier,
                account_number,
                bill_addr_json,
                status,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW()
            )
        ");

        $stmt->execute([
            $data['qbo_company_id'],
            $data['display_name'],
            $data['given_name'] ?? null,
            $data['family_name'] ?? null,
            $data['title'] ?? null,
            $data['suffix'] ?? null,
            $data['company_name'] ?? null,
            $data['print_on_check_name'] ?? null,
            $data['email'] ?? null,
            $data['phone'] ?? null,
            $data['mobile'] ?? null,
            $data['website'] ?? null,
            $data['tax_identifier'] ?? null,
            $data['account_number'] ?? null,
            isset($data['bill_addr'])
                ? json_encode($data['bill_addr'])
                : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Mark vendor as synced
     */
    public function markSynced(
        int $id,
        string $qboId,
        string $syncToken
    ): void {
        $stmt = $this->pdo->prepare("
            UPDATE qbo_vendors
            SET
                qbo_id = ?,
                sync_token = ?,
                status = 'synced',
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
            UPDATE qbo_vendors
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
     * Find vendor by local ID
     */
    public function find(int $id): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM qbo_vendors WHERE id = ?"
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Find vendor by QBO ID
     */
    public function findByQboId(string $qboId): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM qbo_vendors WHERE qbo_id = ?"
        );
        $stmt->execute([$qboId]);

        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Vendors pending sync or retry
     */
    public function getPending(int $maxRetries = 5): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM qbo_vendors
            WHERE status IN ('pending', 'failed')
              AND retry_count < :maxRetries
            ORDER BY last_attempt_at ASC
        ");

        $stmt->execute([':maxRetries' => $maxRetries]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
