<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboCompanies extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('qbo_companies');
        if ($table->exists()) {
            return;
        }
        
        $table
            ->addColumn('realm_id', 'string', ['limit' => 50])
            ->addColumn('access_token', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('refresh_token', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('token_expires_at', 'datetime', ['null' => true])
            ->addColumn('environment', 'enum', ['values' => ['sandbox', 'production'], 'default' => 'sandbox'])
            ->addTimestamps()
            ->addIndex(['realm_id'], ['unique' => true])
            ->create();
    }
}
