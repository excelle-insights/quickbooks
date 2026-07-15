<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboJournalEntryRepository
{
    private string $table;

    public function __construct(private PDO $pdo)
    {
        $this->table = ($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo') . '_journal_entries';
    }

    /**
     * Create a local journal entry header (before QBO sync)
     */
    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (
                local_id,
                qbo_company_id,
                txn_date,
                doc_number,
                currency,
                status,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, 'pending', NOW(), NOW()
            )
        ");

        $stmt->execute([
            $data['local_id'],
            $data['qbo_company_id'],
            $data['txn_date'],
            $data['doc_number'] ?? null,
            $data['currency'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Mark journal entry as synced
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
                last_attempt_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$qboId, $syncToken, $id]);
    }

    /**
     * Mark journal entry sync failure
     */
    public function markFailed(int $id, string $error): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->table}
            SET
                status = 'failed',
                retry_count = retry_count + 1,
                error_message = :error,
                last_attempt_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':error' => $error,
            ':id'    => $id,
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
     * Update local journal entry header
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->table}
            SET
                txn_date = ?,
                doc_number = ?,
                currency = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $data['txn_date'],
            $data['doc_number'] ?? null,
            $data['currency'] ?? null,
            $id,
        ]);
    }

    /**
     * Find by local_id (the source record ID)
     */
    public function findByLocalId(int $localId): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE local_id = ?"
        );
        $stmt->execute([$localId]);

        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    /**
     * Entries pending sync or retry
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
