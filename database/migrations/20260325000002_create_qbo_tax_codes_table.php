<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboTaxCodesTable extends AbstractMigration
{
    public function change(): void
    {
        $prefix = ($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo');
        // qbo_tax_codes — synced FROM QuickBooks (QBO is source of truth)
        if (!$this->hasTable($prefix . '_tax_codes')) {
            $this->table($prefix . '_tax_codes')
                ->addColumn('qbo_company_id', 'integer', ['null' => false])
                ->addColumn('qbo_id',          'string',  ['null' => true,  'comment' => 'QuickBooks TaxCode Id'])
                ->addColumn('name',            'string',  ['null' => false, 'comment' => 'e.g. VAT, EXEMPT, ZERO'])
                ->addColumn('description',     'string',  ['null' => true])
                ->addColumn('taxable',         'boolean', ['null' => false, 'default' => true])
                ->addColumn('active',          'boolean', ['null' => false, 'default' => true])
                ->addColumn('sync_token',      'string',  ['null' => true])
                ->addColumn('status',          'string',  ['null' => false, 'default' => 'pending'])
                ->addColumn('error_message',   'text',    ['null' => true])
                ->addColumn('last_attempt_at', 'timestamp', ['null' => true])
                ->addTimestamps()
                ->addIndex(['qbo_company_id'])
                ->addIndex(['qbo_id'])
                ->addIndex(['status'])
                ->create();
        }

        // Add tax_code_id to qbo_bill_items
        if ($this->hasTable($prefix . '_bill_items')) {
            $table = $this->table($prefix . '_bill_items');

            if (!$this->hasColumn($prefix . '_bill_items', 'tax_code_id')) {
                $table->addColumn('tax_code_id', 'integer', [
                    'null'    => true,
                    'after'   => 'qbo_class_id',
                    'comment' => 'References qbo_tax_codes.id — optional VAT/tax on this expense line'
                ])->update();
            }
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        $rows = $this->fetchAll("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return count($rows) > 0;
    }
}
