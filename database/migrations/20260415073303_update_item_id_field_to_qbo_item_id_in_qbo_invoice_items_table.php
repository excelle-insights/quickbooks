<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UpdateItemIdFieldToQboItemIdInQboInvoiceItemsTable extends AbstractMigration
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
        if ($this->hasTable($_ENV['QBO_TABLE_PREFIX'] . '_invoice_items')) {
            $table = $this->table($_ENV['QBO_TABLE_PREFIX'] . '_invoice_items');
            if ($table->hasColumn('item_id')) {
                $table->changeColumn('item_id', 'integer', ['comment' => 'Stores the local id of items in QuickBooks Online. References ' . $_ENV['QBO_TABLE_PREFIX'] . '_items.id'])->update();
            }

            if ($table->hasColumn('item_id')) {
                $table->renameColumn('item_id', 'qbo_item_id')->update();
            }
        }
    }
}
