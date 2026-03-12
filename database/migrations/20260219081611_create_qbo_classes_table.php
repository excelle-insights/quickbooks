<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboClassesTable extends AbstractMigration
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
        if (!$this->hasTable('qbo_classes')) {

            $this->table('qbo_classes')
                ->addColumn('qbo_company_id', 'integer', ['null' => false])

                // Core fields
                ->addColumn('name', 'string', ['null' => false])
                ->addColumn('parent_id', 'string', [
                    'null' => true,
                    'comment' => 'References the parent class locally. It references qbo_classes.id'
                ])
                ->addColumn('active', 'boolean', [
                    'default' => true,
                    'null' => false
                ])

                // Sync fields
                ->addColumn('qbo_id', 'string', ['null' => true])
                ->addColumn('sync_token', 'string', ['null' => true])
                ->addColumn('status', 'string', [
                    'default' => 'pending',
                    'null' => false
                ])
                ->addColumn('retry_count', 'integer', [
                    'default' => 0,
                    'null' => false
                ])
                ->addColumn('error_message', 'text', ['null' => true])
                ->addColumn('last_attempt_at', 'timestamp', ['null' => true])

                ->addTimestamps()

                ->addIndex(['qbo_company_id'])
                ->addIndex(['qbo_id'])
                ->addIndex(['status'])

                ->create();
        }
    }
}
