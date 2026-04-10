<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboBankTable extends AbstractMigration
{
    /**
     * Create qbo_bank table for QuickBooks bank account integration
     */
    public function change(): void
    {
        $table = $this->table('qbo_bank', ['id' => false, 'primary_key' => ['id']]);
        
        $table->addColumn('id', 'integer', [
                'identity' => true,
                'signed' => false,
                'comment' => 'Primary key'
            ])
            ->addColumn('qbo_company_id', 'integer', [
                'signed' => false,
                'null' => false,
                'comment' => 'QuickBooks company ID'
            ])
            ->addColumn('qbo_account_id', 'string', [
                'limit' => 50,
                'null' => false,
                'comment' => 'QuickBooks account ID'
            ])
            ->addColumn('local_bank_id', 'integer', [
                'signed' => false,
                'null' => true,
                'comment' => 'Local bank account ID for mapping'
            ])
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Bank account name'
            ])
            ->addColumn('account_type', 'string', [
                'limit' => 50,
                'null' => true,
                'comment' => 'QuickBooks account type'
            ])
            ->addColumn('account_sub_type', 'string', [
                'limit' => 50,
                'null' => true,
                'comment' => 'QuickBooks account sub type'
            ])
            ->addColumn('current_balance', 'decimal', [
                'precision' => 15,
                'scale' => 2,
                'default' => 0.00,
                'null' => false,
                'comment' => 'Current account balance'
            ])
            ->addColumn('bank_account_number', 'string', [
                'limit' => 50,
                'null' => true,
                'comment' => 'Bank account number'
            ])
            ->addColumn('routing_number', 'string', [
                'limit' => 20,
                'null' => true,
                'comment' => 'Bank routing number'
            ])
            ->addColumn('active', 'boolean', [
                'default' => true,
                'null' => false,
                'comment' => 'Whether the account is active'
            ])
            ->addColumn('sync_token', 'string', [
                'limit' => 50,
                'null' => true,
                'comment' => 'QuickBooks sync token'
            ])
            ->addColumn('status', 'enum', [
                'values' => ['pending', 'synced', 'failed'],
                'default' => 'pending',
                'null' => false,
                'comment' => 'Sync status'
            ])
            ->addColumn('retry_count', 'integer', [
                'default' => 0,
                'null' => false,
                'comment' => 'Number of sync retry attempts'
            ])
            ->addColumn('error_message', 'text', [
                'null' => true,
                'comment' => 'Error message if sync failed'
            ])
            ->addColumn('created_at', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'null' => false,
                'comment' => 'Record creation timestamp'
            ])
            ->addColumn('updated_at', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
                'null' => false,
                'comment' => 'Record last update timestamp'
            ])
            ->addIndex(['qbo_company_id'], ['name' => 'idx_qbo_bank_company'])
            ->addIndex(['qbo_account_id'], ['name' => 'idx_qbo_bank_account'])
            ->addIndex(['local_bank_id'], ['name' => 'idx_qbo_bank_local'])
            ->addIndex(['status'], ['name' => 'idx_qbo_bank_status'])
            ->addIndex(['qbo_company_id', 'qbo_account_id'], ['unique' => true, 'name' => 'uk_qbo_bank_company_account'])
            ->create();
    }
}
