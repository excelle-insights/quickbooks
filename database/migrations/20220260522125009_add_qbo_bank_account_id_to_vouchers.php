<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Adds qbo_bank_account_id to supplier_payment_vouchers so the QBO bank
 * account chosen at voucher creation is remembered and used at sync time.
 */
final class AddQboBankAccountIdToVouchers extends AbstractMigration
{
    public function up(): void
    {
        $table = 'supplier_payment_vouchers';

        if ($this->hasTable($table) && !$this->table($table)->hasColumn('qbo_bank_account_id')) {
            $this->table($table)
                ->addColumn('qbo_bank_account_id', 'string', [
                    'limit'   => 64,
                    'null'    => true,
                    'after'   => 'bank_id',
                    'comment' => 'QBO bank account ID used when syncing this payment to QuickBooks',
                ])
                ->update();
        }
    }

    public function down(): void
    {
        $table = 'supplier_payment_vouchers';

        if ($this->hasTable($table) && $this->table($table)->hasColumn('qbo_bank_account_id')) {
            $this->table($table)
                ->removeColumn('qbo_bank_account_id')
                ->save();
        }
    }
}
