<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLocalTaxIdFieldToQboTaxCodesTable extends AbstractMigration
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
        $tableName = array_key_exists('QBO_TABLE_PREFIX', $_ENV) ? $_ENV['QBO_TABLE_PREFIX'] . '_tax_codes' : 'qbo_tax_codes';

        if ($this->hasTable($tableName)) {
            $table = $this->table($tableName);
            if (!$table->hasColumn('local_tax_id')) {
                $table->addColumn('local_tax_id', 'integer', ['null' => true, 'after' => 'qbo_id', 'comment' => 'Local tax code ID for reference'])
                    ->update();
            }
        }
    }
}
