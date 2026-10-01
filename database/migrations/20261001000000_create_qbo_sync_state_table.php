<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboSyncStateTable extends AbstractMigration
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
        $prefix = $_ENV['QBO_TABLE_PREFIX'] ?? 'qbo';
        $table  = $prefix . '_sync_state';

        if (!$this->hasTable($table)) {
            $this->table($table)
                ->addColumn('item', 'string', ['limit' => 100, 'null' => false])
                ->addColumn('last_synced_at', 'datetime', ['null' => true])
                ->addColumn('updated_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['item'], ['unique' => true])
                ->create();
        }
    }
}
