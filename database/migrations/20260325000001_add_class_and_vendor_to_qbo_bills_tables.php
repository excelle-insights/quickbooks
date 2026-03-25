<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddClassAndVendorToQboBillsTables extends AbstractMigration
{
    public function change(): void
    {
        // Add qbo_class_id to bill items (mirrors invoice items)
        if ($this->hasTable('qbo_bill_items')) {
            $table = $this->table('qbo_bill_items');

            if (!$this->hasColumn('qbo_bill_items', 'qbo_class_id')) {
                $table->addColumn('qbo_class_id', 'integer', [
                    'null'    => true,
                    'after'   => 'bill_id',
                    'comment' => 'References qbo_classes.id for expense classification'
                ])->update();
            }
        }

        // Add qbo_vendor_id (local FK) to qbo_bills so we can resolve vendor_qbo_id at sync time
        if ($this->hasTable('qbo_bills')) {
            $table = $this->table('qbo_bills');

            if (!$this->hasColumn('qbo_bills', 'vendor_qbo_id')) {
                $table->addColumn('vendor_qbo_id', 'string', [
                    'null'    => true,
                    'after'   => 'qbo_vendor_id',
                    'comment' => 'QuickBooks Vendor ID resolved at sync time'
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
