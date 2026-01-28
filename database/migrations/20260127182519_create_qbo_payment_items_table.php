<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboPaymentItemsTable extends AbstractMigration
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
        if ($this->hasTable('qbo_payment_items')) {
            return;
        }

        $this->table('qbo_payment_items')
            ->addColumn('payment_id', 'integer') // FK → qbo_payments.id

            ->addColumn('qbo_invoice_id', 'string')
            ->addColumn('amount', 'decimal', [
                'precision' => 12,
                'scale' => 2
            ])

            ->addColumn('created_at', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP'
            ])

            ->addIndex(['payment_id'])
            ->addIndex(['qbo_invoice_id'])

            ->addForeignKey(
                'payment_id',
                'qbo_payments',
                'id',
                [
                    'delete' => 'CASCADE',
                    'update' => 'NO_ACTION'
                ]
            )

            ->create();
    }
}
