<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboInvoices extends AbstractMigration
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
        $table = $this->table($_ENV['QBO_CLIENT_ID'] ?? 'qbo'.'_invoices');

        $table
            ->addColumn('qbo_company_id', 'integer', [
                'null' => false,
                'comment' => 'References '.$_ENV['QBO_CLIENT_ID'] ?? 'qbo' .'_companies.id',
                'signed' => false,
            ])
            ->addColumn('qbo_customer_id', 'integer', [
                'null' => false,
                'comment' => 'References '.$_ENV['QBO_CLIENT_ID'] ?? 'qbo' .'_customers.id',
                'signed' => false,
            ])
            ->addColumn('invoice_number', 'string', [
                'limit' => 50,
                'null' => true,
                'comment' => 'Local invoice reference',
            ])
            ->addColumn('status', 'string', [
                'limit' => 20,
                'default' => 'draft',
                'comment' => 'draft | synced | failed',
            ])
            ->addColumn('qbo_id', 'string', [
                'limit' => 50,
                'null' => true,
                'comment' => 'QuickBooks Online Invoice ID',
            ])
            ->addColumn('sync_token', 'string', [
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('invoice_date', 'date', [
                'null' => false,
            ])
            ->addColumn('due_date', 'date', [
                'null' => true,
            ])
            ->addColumn('currency', 'string', [
                'limit' => 10,
                'default' => 'KES',
            ])
            ->addColumn('total', 'decimal', [
                'precision' => 15,
                'scale' => 2,
                'null' => true,
                'comment' => 'Total amount from QBO',
            ])
            ->addTimestamps()
            ->addIndex(['qbo_id'], [
                'unique' => true,
                'name' => 'idx_qbo_invoice_qbo_id',
            ])
            ->addIndex(['status'], [
                'name' => 'idx_qbo_invoice_status',
            ])
            ->addForeignKey(
                'qbo_company_id',
                'qbo_companies',
                'id',
                ['delete' => 'CASCADE']
            )
            ->addForeignKey(
                'qbo_customer_id',
                'qbo_customers',
                'id',
                ['delete' => 'CASCADE']
            )
            ->create();
    }
}
