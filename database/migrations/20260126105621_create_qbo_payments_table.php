<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboPaymentsTable extends AbstractMigration
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
        if ($this->hasTable('qbo_payments')) {
            return;
        }

        $this->table('qbo_payments')
            ->addColumn('pay_id', 'integer', ['comment' => 'Holds local id of payment being inserted. Used to check duplicates']) // local payment id
            ->addColumn('qbo_id', 'integer', ['null' => true, 'comment' => 'References Quickbooks Invoice->Id'])

            ->addColumn('qbo_customer_id', 'string')
            ->addColumn('total_amount', 'decimal', [
                'precision' => 12,
                'scale' => 2
            ])

            ->addColumn('txn_date', 'date', ['null' => true])
            ->addColumn('payment_ref', 'string', [
                'limit' => 255,
                'null' => true
            ])

            ->addColumn('deposit_account_id', 'string', [
                'limit' => 50,
                'null' => true
            ])

            ->addColumn('private_note', 'string', [
                'limit' => 255,
                'null' => true
            ])

            ->addColumn('status', 'string', [
                'default' => 'pending',
                'comment' => "pending | synced | failed"
                // PENDING | SYNCED | FAILED
            ])

            ->addColumn('retry_count', 'integer', ['default' => 0])
            ->addColumn('error_message', 'text', ['null' => true])
            ->addColumn('last_attempt_at', 'datetime')
            ->addColumn('created_at', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP'
            ])
            ->addColumn('updated_at', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'
            ])

            ->addIndex(['pay_id'], ['unique' => true])
            ->addIndex(['status'])
            ->addIndex(['qbo_customer_id'])

            ->create();
    }
}
