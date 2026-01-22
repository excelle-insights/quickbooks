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
        $table = $this->table($_ENV['QBO_CLIENT_ID'] ?? 'qbo' . '_invoice_items');

        $table
            ->addColumn('invoice_id', 'integer', [
                'null' => false,
                'comment' => 'References '.$_ENV['QBO_CLIENT_ID'] ?? 'qbo' .'_invoices.id',
                'signed' => false,
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
                'qbo_invoices',
                'id',
                ['delete' => 'CASCADE']
            )
            ->create();
    }
}
