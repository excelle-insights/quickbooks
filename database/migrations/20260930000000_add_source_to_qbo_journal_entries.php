<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSourceToQboJournalEntries extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function up(): void
    {
        $prefix = $_ENV['QBO_TABLE_PREFIX'] ?? 'qbo';

        $table = $prefix . '_journal_entries';

        if ($this->hasTable($table) && !$this->table($table)->hasColumn('source')) {
            $this->execute("ALTER TABLE $table ADD COLUMN source VARCHAR(10) NOT NULL DEFAULT 'pushed' AFTER status");
        }
    }

    public function down(): void
    {
        $prefix = $_ENV['QBO_TABLE_PREFIX'] ?? 'qbo';

        $table = $prefix . '_journal_entries';

        if ($this->hasTable($table) && $this->table($table)->hasColumn('source')) {
            $this->execute("ALTER TABLE $table DROP COLUMN source");
        }
    }
}
