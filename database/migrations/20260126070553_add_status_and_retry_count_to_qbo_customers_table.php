<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddStatusAndRetryCountToQboCustomersTable extends AbstractMigration
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

        if (!$table->hasColumn('status')) {
            $table->addColumn('status', 'string', [
                'limit' => 20,
                'default' => 'pending',
                'after' => 'line',
                'comment' => 'pending | synced | failed',
            ])->update();
        }

        if (!$table->hasColumn('retry_count')) {
            $table->addColumn('retry_count', 'integer', [
                'default' => 0,
                'after' => 'status'
            ])->update();
        }

        if (!$table->hasColumn('error_message')) {
            $table->addColumn('error_message', 'string', [
                'after' => 'retry_count',
            ])->update();
        }
        if (!$table->hasColumn('last_attempt_at')) {
            $table->addColumn('last_attempt_at', 'datetime', [
                'after' => 'error_message'
            ])->update();
        }
    }
}
