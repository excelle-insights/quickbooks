<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateApiAccessTokensTable extends AbstractMigration
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
        if (!$this->hasTable('api_access_tokens')) {
            $table = $this->table('api_access_tokens');
            $table
                ->addColumn('app', 'string', ['limit' => 100])
                ->addColumn('access_token', 'text')
                ->addColumn('user_id', 'string', ['limit' => 100])
                ->addTimestamps()
                ->addIndex(['app', 'user_id'], ['unique' => true])
                ->create();
        }
    }
}
