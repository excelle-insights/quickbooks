<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class FixQboAccountsColumns extends AbstractMigration
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
        if (!$this->hasTable($prefix . '_accounts')) {
            throw new RuntimeException('Table qbo_accounts does not exist.');
        }

        $table = $this->table($prefix . '_accounts');

        // Define columns: type is separate, options contain only Phinx column options
        $columns = [
            'qbo_company_id' => ['type' => 'integer', 'null' => false, 'default' => 1, 'after' => 'id'],
            'account_type'   => ['type' => 'string',  'null' => false, 'default' => 'Income', 'after' => 'name'],
            'account_sub_type' => ['type' => 'string', 'null' => true, 'after' => 'account_type'],
            'classification' => ['type' => 'string', 'null' => true, 'after' => 'account_sub_type'],
            'description'    => ['type' => 'text',   'null' => true, 'after' => 'classification'],
            'sub_account'    => ['type' => 'boolean','null' => false, 'default' => false, 'after' => 'description'],
            'parent_qbo_id'  => ['type' => 'string', 'null' => true, 'after' => 'sub_account'],
            'currency'       => ['type' => 'string', 'null' => true, 'after' => 'parent_qbo_id'],
            'active'         => ['type' => 'boolean','null' => false, 'default' => true, 'after' => 'currency'],
            'status'         => ['type' => 'string', 'null' => false, 'default' => 'pending', 'after' => 'active'],
            'retry_count'    => ['type' => 'integer','null' => false, 'default' => 0, 'after' => 'status'],
            'sync_token'     => ['type' => 'string', 'null' => true, 'after' => 'retry_count'],
            'qbo_id'         => ['type' => 'string', 'null' => true, 'after' => 'sync_token'],
            'error_message'  => ['type' => 'text',   'null' => true, 'after' => 'qbo_id'],
            'last_attempt_at'=> ['type' => 'timestamp', 'null' => true, 'after' => 'error_message'],
        ];

        foreach ($columns as $name => $opts) {
            $type = $opts['type'];
            unset($opts['type']); // remove 'type' key from options
            if (!$table->hasColumn($name)) {
                $table->addColumn($name, $type, $opts)->update();
            }
        }

        // Add timestamps if missing
        if (!$table->hasColumn('created_at')) {
            $table->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])->update();
        }

        if (!$table->hasColumn('updated_at')) {
            $table->addColumn('updated_at', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'update'  => 'CURRENT_TIMESTAMP'
            ])->update();
        }
    }
}
