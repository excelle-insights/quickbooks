<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddQboTaxIdToQboInvoiceItemsTable extends AbstractMigration
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
        $tableName = $_ENV['QBO_TABLE_PREFIX'] . '_invoice_items';
        if ($this->hasTable($tableName)) {
            $table = $this->table($tableName);
            if (!$table->hasColumn('qbo_tax_id')) {
                $table->addColumn('qbo_tax_id', 'integer', ['null' => true, 'after' => 'qbo_item_id', 'comment' => 'QBO tax code ID for local reference. References qbo_tax_codes.id'])
                    ->update();
            }
        }
    }
}
