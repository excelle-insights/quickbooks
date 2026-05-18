<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboExpensesTables extends AbstractMigration
{
    public function change(): void
    {
        $prefix = ($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo');
        /**
         * qbo_expenses
         * Maps to QBO Purchase entity (direct expense / procurement)
         */
        if (!$this->hasTable($prefix . '_expenses')) {
            $this->table($prefix . '_expenses')
                ->addColumn('qbo_company_id',     'integer', ['null' => false])
                ->addColumn('qbo_vendor_id',       'integer', ['null' => true,  'comment' => 'References qbo_vendors.id (Payee)'])
                ->addColumn('payment_account_qbo_id', 'string', ['null' => false, 'comment' => 'QBO bank/credit account ID used to pay'])
                ->addColumn('payment_method',      'string',  ['null' => true])
                ->addColumn('payment_type',        'string',  ['null' => false, 'default' => 'Cash', 'comment' => 'Cash | Check | CreditCard'])
                ->addColumn('txn_date',            'date',    ['null' => true])
                ->addColumn('ref_number',          'string',  ['null' => true,  'comment' => 'Ref no. on the form'])
                ->addColumn('currency',            'string',  ['null' => true])
                ->addColumn('memo',                'text',    ['null' => true])
                ->addColumn('qbo_id',              'string',  ['null' => true])
                ->addColumn('sync_token',          'string',  ['null' => true])
                ->addColumn('status',              'string',  ['null' => false, 'default' => 'pending'])
                ->addColumn('retry_count',         'integer', ['null' => false, 'default' => 0])
                ->addColumn('error_message',       'text',    ['null' => true])
                ->addColumn('last_attempt_at',     'timestamp', ['null' => true])
                ->addTimestamps()
                ->create();
        }

        /**
         * qbo_expense_items
         * Category detail lines on the expense form
         */
        if (!$this->hasTable($prefix . '_expense_items')) {
            $this->table($prefix . '_expense_items')
                ->addColumn('expense_id',      'integer', ['null' => false, 'comment' => 'References qbo_expenses.id'])
                ->addForeignKey('expense_id', $prefix . '_expenses', 'id', ['delete' => 'CASCADE'])
                ->create();
        }
    }
}
