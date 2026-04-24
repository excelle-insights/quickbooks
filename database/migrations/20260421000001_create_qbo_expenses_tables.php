<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboExpensesTables extends AbstractMigration
{
    public function change(): void
    {
        /**
         * qbo_expenses
         * Maps to QBO Purchase entity (direct expense / procurement)
         */
        if (!$this->hasTable('qbo_expenses')) {
            $this->table('qbo_expenses')
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
        if (!$this->hasTable('qbo_expense_items')) {
            $this->table('qbo_expense_items')
                ->addColumn('expense_id',      'integer', ['null' => false, 'signed' => false, 'comment' => 'References qbo_expenses.id'])
                ->addColumn('account_qbo_id',  'string',  ['null' => false, 'comment' => 'QBO expense account (Category)'])
                ->addColumn('qbo_class_id',    'integer', ['null' => true,  'comment' => 'References qbo_classes.id'])
                ->addColumn('tax_code_id',     'integer', ['null' => true,  'comment' => 'References qbo_tax_codes.id'])
                ->addColumn('amount',          'decimal', ['precision' => 15, 'scale' => 2, 'null' => false])
                ->addColumn('description',     'string',  ['null' => true])
                ->addTimestamps()
                ->addForeignKey('expense_id', 'qbo_expenses', 'id', ['delete' => 'CASCADE'])
                ->create();
        }
    }
}
