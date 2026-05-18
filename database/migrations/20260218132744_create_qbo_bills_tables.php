<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboBillsTables extends AbstractMigration
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
        /**
         * ------------------------------
         * qbo_bills
         * ------------------------------
         */
        if (!$this->hasTable($prefix . '_bills')) {
            $this->table($prefix . '_bills')
                ->addColumn('qbo_company_id', 'integer', ['null' => false])
                ->addColumn('qbo_vendor_id', 'integer', ['null' => false, 'comment' => 'References qbo_vendors.id'])
                ->addColumn('txn_date', 'date', ['null' => true])
                ->addColumn('currency', 'string', ['null' => true])
                ->addColumn('qbo_id', 'string', ['null' => true])
                ->addColumn('sync_token', 'string', ['null' => true])
                ->addColumn('status', 'string', [
                    'null' => false,
                    'default' => 'pending'
                ])
                ->addColumn('retry_count', 'integer', [
                    'null' => false,
                    'default' => 0
                ])
                ->addColumn('error_message', 'text', ['null' => true])
                ->addColumn('last_attempt_at', 'timestamp', ['null' => true])
                ->addTimestamps()
                ->create();
        }

        /**
         * ------------------------------
         * qbo_bill_items
         * ------------------------------
         */
        if (!$this->hasTable($prefix . '_bill_items')) {
            $this->table($prefix . '_bill_items')
                ->addColumn('bill_id', 'integer', ['null' => false, 'signed' => false, 'comment' => 'References qbo_bills.id'])
                ->addColumn('account_qbo_id', 'string', ['null' => false, 'comment' => 'QuickBooks Online Account ID'])
                ->addColumn('amount', 'decimal', [
                    'precision' => 15,
                    'scale' => 2,
                    'null' => false
                ])
                ->addColumn('description', 'string', ['null' => true])
                ->addTimestamps()
                ->addForeignKey(
                    'bill_id',
                    $prefix . '_bills',
                    'id',
                    ['delete' => 'CASCADE']
                )
                ->create();
        }
    }
}
