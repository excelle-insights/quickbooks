<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLocalIdentifiersToQboTables extends AbstractMigration
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
        if (!$table->hasColumn('local_id')) {
            $table->addColumn('local_id', 'integer', [
                'after' => 'id',
                'comment' => 'Id of local record used to create this entity'
            ])->update();
        }

        $table = $this->table('qbo_invoices');
        if (!$table->hasColumn('local_id')) {
            $table->addColumn('local_id', 'integer', [
                'after' => 'id',
                'comment' => 'Id of local record used to create this entity'
            ])->update();
        }

        $table = $this->table('qbo_payments');
        if (!$table->hasColumn('local_id')) {
            $table->addColumn('local_id', 'integer', [
                'after' => 'id',
                'comment' => 'Id of local record used to create this entity'
            ])->update();
        }
    }
}
