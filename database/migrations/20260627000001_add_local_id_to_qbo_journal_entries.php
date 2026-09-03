<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLocalIdToQboJournalEntries extends AbstractMigration
{
    public function change(): void
    {
        $prefix = ($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo');
        $table = $this->table($prefix . '_journal_entries');

        if (!$table->hasColumn('local_id')) {
            $table
                ->addColumn('local_id', 'integer', [
                    'after' => 'id',
                    'null' => true,
                    'comment' => 'Id of local record used to create this journal entry',
                ])
                ->update();
        }
    }
}
