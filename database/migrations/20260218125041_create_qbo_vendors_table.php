<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboVendorsTable extends AbstractMigration
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
        if ($this->hasTable($prefix . '_vendors')) {
            return;
        }

        $table = $this->table($prefix . '_vendors');

        $table
            ->addColumn('qbo_company_id', 'integer', ['null' => false])
            ->addColumn('display_name', 'string', ['null' => false])

            ->addColumn('given_name', 'string', ['null' => true])
            ->addColumn('family_name', 'string', ['null' => true])
            ->addColumn('title', 'string', ['null' => true])
            ->addColumn('suffix', 'string', ['null' => true])

            ->addColumn('company_name', 'string', ['null' => true])
            ->addColumn('print_on_check_name', 'string', ['null' => true])

            ->addColumn('email', 'string', ['null' => true])
            ->addColumn('phone', 'string', ['null' => true])
            ->addColumn('mobile', 'string', ['null' => true])
            ->addColumn('website', 'string', ['null' => true])

            ->addColumn('tax_identifier', 'string', ['null' => true])
            ->addColumn('account_number', 'string', ['null' => true])

            ->addColumn('bill_addr_json', 'json', ['null' => true])

            ->addColumn('qbo_id', 'string', ['null' => true])
            ->addColumn('sync_token', 'string', ['null' => true])

            ->addColumn('status', 'string', [
                'null'    => false,
                'default' => 'pending'
            ])
            ->addColumn('retry_count', 'integer', [
                'null'    => false,
                'default' => 0
            ])
            ->addColumn('error_message', 'text', ['null' => true])
            ->addColumn('last_attempt_at', 'timestamp', ['null' => true])

            ->addTimestamps()
            ->create();
    }
}
