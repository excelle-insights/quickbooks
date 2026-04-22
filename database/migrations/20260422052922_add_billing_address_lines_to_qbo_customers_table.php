<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddBillingAddressLinesToQboCustomersTable extends AbstractMigration
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
        $tableName = array_key_exists('QBO_TABLE_PREFIX', $_ENV) ? $_ENV['QBO_TABLE_PREFIX'] . '_customers' : 'qbo_customers';
        if (!$this->hasTable($tableName)) {
            error_log("Table $tableName does not exist. Skipping migration.");
            return;
        }

        $table = $this->table($tableName);
        if (!$table->hasColumn('line1')) {
            $table->addColumn('line1', 'string', ['limit' => 255, 'null' => true, 'after' => 'line'])
                ->update();
        }
        if (!$table->hasColumn('line2')) {
            $table->addColumn('line2', 'string', ['limit' => 255, 'null' => true, 'after' => 'line1'])
                ->update();
        }

    }
}
