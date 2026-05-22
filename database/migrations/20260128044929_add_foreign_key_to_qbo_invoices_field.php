<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddForeignKeyToQboInvoicesField extends AbstractMigration
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
        $prefix = ($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo');
        $table = $this->table($prefix . '_payment_items');
        if ($table->hasColumn('qbo_invoice_id')) {
            $table
                ->changeColumn('qbo_invoice_id', 'integer', [
                    'signed' => false,
                    'comment' => 'References id in qbo_invoices'
                ])
                ->addForeignKey(
                    'qbo_invoice_id',
                    $prefix . '_invoices',
                    'id',
                    [
                        'delete' => 'CASCADE',
                        'update' => 'NO_ACTION'
                    ]
                )->update();
        }
    }
}
