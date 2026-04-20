<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddKraPinFieldToQboCustomersTable extends AbstractMigration
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
        $tableName = $_ENV['QBO_TABLE_PREFIX'] . '_customers';

        if (!$this->hasTable($tableName)) {
            return;
        }

        $table = $this->table($tableName);
        $table->addColumn('kra_pin', 'string', ['null' => true, 'after' => 'postal_code'])
              ->update();
    }
}
