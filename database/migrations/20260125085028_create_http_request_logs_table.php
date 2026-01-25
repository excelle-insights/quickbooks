<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateHttpRequestLogsTable extends AbstractMigration
{
    public function change(): void
    {
        if ($this->hasTable('http_request_logs')) {
            return;
        }

        $table = $this->table('http_request_logs');
        $table
            ->addColumn('level', 'string', ['limit' => 10, 'default' => 'INFO'])
            ->addColumn('message', 'text', ['null' => true])
            ->addColumn('method', 'string', ['limit' => 10])
            ->addColumn('url', 'text')
            ->addColumn('request_headers', 'text', ['null' => true])
            ->addColumn('request_body', 'text', ['null' => true])
            ->addColumn('response_status', 'integer', ['null' => true])
            ->addColumn('response_headers', 'text', ['null' => true])
            ->addColumn('response_body', 'text', ['null' => true])
            ->addColumn('error_message', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', [
                'null' => true,
                'default' => null
            ])
            ->addIndex(['method'])
            ->addIndex(['response_status'])
            ->create();
    }
}
