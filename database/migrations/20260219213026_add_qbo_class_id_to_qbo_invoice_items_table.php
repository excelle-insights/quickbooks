<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddQboClassIdToQboInvoiceItemsTable extends AbstractMigration
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
        $table = $this->table('qbo_invoice_items');
        $table->addColumn('qbo_class_id', 'integer', [
            'null' => true,
            'after' => 'qbo_invoice_id',
            'comment' => 'Stores the QBO Class ID for the invoice item. References qbo_classes.id'
        ]);
        $table->update();
    }
}
