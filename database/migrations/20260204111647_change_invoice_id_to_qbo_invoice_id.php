<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ChangeInvoiceIdToQboInvoiceId extends AbstractMigration
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
    public function change(): void
    {
        $table = $this->table(($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo') . '_invoice_items');
        if($table->hasColumn('invoice_id')) {
            $table->renameColumn('invoice_id', 'qbo_invoice_id')
                ->update();
        }
    }
}
