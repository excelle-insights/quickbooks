<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboJournalEntriesTables extends AbstractMigration
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
        // ------------------------------
        // 1️⃣ qbo_journal_entries table
        // ------------------------------
        if (!$this->hasTable('qbo_journal_entries')) {
            $table = $this->table('qbo_journal_entries');

            $table
                ->addColumn('qbo_company_id', 'integer', ['null' => false, 'after' => 'id'])
                ->addColumn('txn_date', 'date', ['null' => false])
                ->addColumn('doc_number', 'string', ['null' => true])
                ->addColumn('currency', 'string', ['null' => true])
                ->addColumn('qbo_id', 'string', ['null' => true])
                ->addColumn('sync_token', 'string', ['null' => true])
                ->addColumn('status', 'string', ['null' => false, 'default' => 'pending'])
                ->addColumn('retry_count', 'integer', ['null' => false, 'default' => 0])
                ->addColumn('error_message', 'text', ['null' => true])
                ->addColumn('last_attempt_at', 'timestamp', ['null' => true])
                ->addTimestamps() 
                ->create();
        }

        // ------------------------------
        // 2️⃣ qbo_journal_entry_lines table
        // ------------------------------
        if (!$this->hasTable('qbo_journal_entry_lines')) {
            $table = $this->table('qbo_journal_entry_lines');

            $table
                ->addColumn('journal_entry_id', 'integer', ['null' => false, 'signed' => false])
                ->addColumn('account_qbo_id', 'string', ['null' => false, 'comment' => 'QBO ID of the account. Should reference the qbo_id in qbo_accounts table'])
                ->addColumn('account_name', 'string', ['null' => true])
                ->addColumn('debit', 'decimal', ['precision' => 15, 'scale' => 2, 'default' => 0])
                ->addColumn('credit', 'decimal', ['precision' => 15, 'scale' => 2, 'default' => 0])
                ->addColumn('description', 'text', ['null' => true])
                ->addTimestamps()
                ->addForeignKey('journal_entry_id', 'qbo_journal_entries', 'id', [
                    'delete'=> 'CASCADE',
                    'update'=> 'NO_ACTION'
                ])
                ->create();
        }
    }
}
