<?php

namespace ExcelleInsights\QuickBooks\Repositories;

use PDO;

class QboJournalEntryLineRepository
{
    private string $table;

    public function __construct(private PDO $pdo)
    {
        $this->table = ($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo') . '_journal_entry_lines';
    }

    /**
     * Add a line to a journal entry
     */
    public function create(int $journalEntryId, array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table} (
                journal_entry_id,
                account_qbo_id,
                account_name,
                debit,
                credit,
                description,
                created_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ");

        $stmt->execute([
            $journalEntryId,
            $data['account_qbo_id'],
            $data['account_name'] ?? null,
            $data['debit'] ?? 0,
            $data['credit'] ?? 0,
            $data['description'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Get all lines for a journal entry
     */
    public function getByJournalEntry(int $journalEntryId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM {$this->table}
            WHERE journal_entry_id = ?
            ORDER BY id ASC
        ");

        $stmt->execute([$journalEntryId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete all lines for a journal entry
     * (used when rebuilding lines before resync)
     */
    public function deleteByJournalEntry(int $journalEntryId): void
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM {$this->table}
            WHERE journal_entry_id = ?
        ");

        $stmt->execute([$journalEntryId]);
    }
}
