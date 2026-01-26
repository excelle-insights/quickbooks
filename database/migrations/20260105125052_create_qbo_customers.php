<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboCustomers extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('qbo_customers');

        if (!$table->exists()) {
            $table
                ->addColumn('qbo_company_id', 'integer', ['signed' => false, 'null' => true, 'comment' => 'References qbo_companies.id'])
                ->addColumn('display_name', 'string', ['limit' => 255])
                ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('phone', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('active', 'boolean', ['default' => true])
                ->addColumn('qbo_id', 'string', ['limit' => 50, 'null' => true, 'comment' => 'QuickBooks Online ID'])
                ->addColumn('sync_token', 'string', ['limit' => 50, 'null' => true])
                ->addTimestamps() // creates created_at and updated_at
                ->addForeignKey('qbo_company_id', 'qbo_companies', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                ->addIndex(['qbo_id'], ['unique' => true])
                ->create();
        }
    }
}
