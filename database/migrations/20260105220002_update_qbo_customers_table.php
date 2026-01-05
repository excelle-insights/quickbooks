<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UpdateQboCustomersTable extends AbstractMigration
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

        // Add columns if they don't exist
        if (!$table->hasColumn('qbo_company_id')) {
            $table->addColumn('qbo_company_id', 'integer', [
                'null' => false,
                'comment' => 'References qbo_companies.id'
            ]);
        }

        if (!$table->hasColumn('name')) {
            $table->addColumn('name', 'string', ['limit' => 255]);
        }

        if (!$table->hasColumn('email')) {
            $table->addColumn('email', 'string', ['limit' => 255, 'null' => true]);
        }

        if (!$table->hasColumn('phone')) {
            $table->addColumn('phone', 'string', ['limit' => 50, 'null' => true]);
        }

        if (!$table->hasColumn('company_name')) {
            $table->addColumn('company_name', 'string', ['limit' => 255, 'null' => true]);
        }

        if (!$table->hasColumn('country')) {
            $table->addColumn('country', 'string', ['limit' => 100, 'null' => true]);
        }

        if (!$table->hasColumn('city')) {
            $table->addColumn('city', 'string', ['limit' => 100, 'null' => true]);
        }

        if (!$table->hasColumn('postal_code')) {
            $table->addColumn('postal_code', 'string', ['limit' => 20, 'null' => true]);
        }

        if (!$table->hasColumn('line')) {
            $table->addColumn('line', 'string', ['limit' => 255, 'null' => true]);
        }

        // Make sure timestamps exist
        if (!$table->hasColumn('created_at')) {
            $table->addTimestamps();
        }

        $table->update();
    }
}
