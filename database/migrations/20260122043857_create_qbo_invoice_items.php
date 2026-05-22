<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboInvoiceItems extends AbstractMigration
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
        $prefix = $_ENV['QBO_TABLE_PREFIX'] ?? 'qbo';
        $table = $this->table($prefix . '_invoice_items');

        if ($table->exists()) {
            return;
        }
        
        $table
            ->addColumn('invoice_id', 'integer', [
                'null' => false,
                'comment' => 'References ' . $prefix . '_invoices.id',
                'signed' => false,
            ])
            ->addColumn('detail_type', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('item_id', 'integer')
            ->addColumn('item_name', 'string', [
                'limit' => 255,
            ])
            ->addColumn('description', 'string', [
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('quantity', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'default' => 1,
            ])
            ->addColumn('unit_price', 'decimal', [
                'precision' => 15,
                'scale' => 2,
                'null' => false,
            ])
            ->addColumn('line_total', 'decimal', [
                'precision' => 15,
                'scale' => 2,
                'null' => true,
                'comment' => 'Calculated locally or from QBO',
            ])
            ->addTimestamps()
            ->addIndex(['invoice_id'], [
                'name' => 'idx_qbo_invoice_items_invoice_id',
            ])
            ->addForeignKey(
                'invoice_id',
                $prefix . '_invoices',
                'id',
                ['delete' => 'CASCADE']
            )
            ->create();
    }
}
