<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddQboParentIdToQboCustomersTable extends AbstractMigration
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
        $table = $this->table('qbo_customers');
        $table->changeColumn('parent_id', 'integer', ['null' => true, 'after' => 'qbo_id', 'comment' => 'Parent Customer ID (local)']);
        if (!$table->hasColumn('qbo_parent_id')) {
            $table->addColumn('qbo_parent_id', 'string', ['null' => true, 'after' => 'qbo_id', 'comment' => 'QuickBooks Parent Customer ID'])
                  ->update();
        }
    }
}
