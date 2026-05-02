<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSourceToQboInvoicesAndPayments extends AbstractMigration
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

        $invoicesTable = $prefix . '_invoices';
        $paymentsTable = $prefix . '_payments';

        if ($this->hasTable($invoicesTable) && !$this->table($invoicesTable)->hasColumn('source')) {
            $this->execute("ALTER TABLE $invoicesTable ADD COLUMN source VARCHAR(10) NOT NULL DEFAULT 'pushed' AFTER status");
        }

        if ($this->hasTable($paymentsTable) && !$this->table($paymentsTable)->hasColumn('source')) {
            $this->execute("ALTER TABLE $paymentsTable ADD COLUMN source VARCHAR(10) NOT NULL DEFAULT 'pushed' AFTER status");
        }
    }

    public function down(): void
    {
        $prefix = $_ENV['QBO_TABLE_PREFIX'] ?? 'qbo';

        $invoicesTable = $prefix . '_invoices';
        $paymentsTable = $prefix . '_payments';

        if ($this->hasTable($invoicesTable) && $this->table($invoicesTable)->hasColumn('source')) {
            $this->execute("ALTER TABLE $invoicesTable DROP COLUMN source");
        }

        if ($this->hasTable($paymentsTable) && $this->table($paymentsTable)->hasColumn('source')) {
            $this->execute("ALTER TABLE $paymentsTable DROP COLUMN source");
        }
    }

}
