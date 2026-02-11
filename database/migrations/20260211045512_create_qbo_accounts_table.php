<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboAccountsTable extends AbstractMigration
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
        $table = $this->table('qbo_accounts', [
            'id' => true,
            'signed' => false,
        ]);

        $table
            // QuickBooks identifiers
            ->addColumn('qbo_id', 'string', [
                'limit' => 64,
                'null'  => true,
            ])
            ->addColumn('sync_token', 'string', [
                'limit' => 20,
                'null'  => true,
            ])

            // Account details
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null'  => false,
            ])
            ->addColumn('account_type', 'string', [
                'limit' => 50,
                'null'  => false,
            ])
            ->addColumn('account_sub_type', 'string', [
                'limit' => 100,
                'null'  => true,
            ])
            ->addColumn('classification', 'string', [
                'limit' => 50,
                'null'  => true,
            ])
            ->addColumn('description', 'text', [
                'null' => true,
            ])

            // Status flags
            ->addColumn('active', 'boolean', [
                'default' => true,
            ])
            ->addColumn('sub_account', 'boolean', [
                'default' => false,
            ])

            // Parent account (for sub-accounts)
            ->addColumn('parent_qbo_id', 'string', [
                'limit' => 64,
                'null'  => true,
            ])

            // Currency
            ->addColumn('currency', 'string', [
                'limit' => 10,
                'null'  => true,
            ])

            // QuickBooks audit timestamps
            ->addColumn('qbo_created_at', 'datetime', [
                'null' => true,
            ])
            ->addColumn('qbo_updated_at', 'datetime', [
                'null' => true,
            ])

            // Local audit timestamps
            ->addColumn('created_at', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
            ])
            ->addColumn('updated_at', 'datetime', [
                'null' => true,
                'update' => 'CURRENT_TIMESTAMP',
            ])

            // Indexes
            ->addIndex(['qbo_id'], [
                'unique' => true,
                'name'   => 'idx_qbo_accounts_qbo_id',
            ])
            ->addIndex(['account_type'], [
                'name' => 'idx_qbo_accounts_account_type',
            ])
            ->addIndex(['active'], [
                'name' => 'idx_qbo_accounts_active',
            ])

            ->create();
    }
}
