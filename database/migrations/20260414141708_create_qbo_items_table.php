<?php

/**
 * File: db/migrations/XXXX_create_qbo_items.php
 *
 * Migration to create the qbo_items table for storing QuickBooks Online
 * Item (Product/Service) records locally. Supports both Category and
 * Service/Inventory/NonInventory types, sub-items via parent_ref, and
 * links to income/expense accounts and classes.
 */

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQboItemsTable extends AbstractMigration
{
    public function change(): void
    {
        $tableName = array_key_exists('QBO_TABLE_PREFIX', $_ENV) ? $_ENV['QBO_TABLE_PREFIX'] . '_items' : 'qbo_items';
        $table = $this->table($tableName);

        $table
            ->addColumn('qbo_company_id', 'integer', [
                'null'     => false,
                'signed'   => false,
                'comment'  => 'References ' . ($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo') . '_companies.id',
            ])
            ->addColumn('name', 'string', [
                'limit'   => 100,
                'null'    => false,
                'comment' => 'Item name in QBO',
            ])
            ->addColumn('type', 'string', [
                'limit'   => 30,
                'null'    => false,
                'comment' => 'Category | Service | Inventory | NonInventory | Group | Fixed Asset',
            ])
            ->addColumn('description', 'text', [
                'null'    => true,
                'comment' => 'Sale/purchase description',
            ])
            ->addColumn('active', 'boolean', [
                'default' => true,
            ])
            ->addColumn('sub_item', 'boolean', [
                'default' => false,
                'comment' => 'True if this is a sub-item',
            ])
            ->addColumn('parent_ref', 'string', [
                'limit'   => 50,
                'null'    => true,
                'comment' => 'QBO ID of the parent item (for sub-items)',
            ])
            ->addColumn('level', 'integer', [
                'null'    => true,
                'default' => 0,
                'signed'  => false,
                'comment' => 'Nesting depth (0 = top-level)',
            ])
            ->addColumn('fully_qualified_name', 'string', [
                'limit'   => 255,
                'null'    => true,
                'comment' => 'Full hierarchy path e.g. Deposit:Deposit etims',
            ])
            ->addColumn('taxable', 'boolean', [
                'default' => false,
            ])
            ->addColumn('unit_price', 'decimal', [
                'precision' => 15,
                'scale'     => 2,
                'default'   => 0,
                'comment'   => 'Default sales price',
            ])
            ->addColumn('purchase_cost', 'decimal', [
                'precision' => 15,
                'scale'     => 2,
                'default'   => 0,
                'comment'   => 'Default purchase cost',
            ])
            ->addColumn('income_account_qbo_id', 'string', [
                'limit'   => 50,
                'null'    => true,
                'comment' => 'QBO IncomeAccountRef value',
            ])
            ->addColumn('expense_account_qbo_id', 'string', [
                'limit'   => 50,
                'null'    => true,
                'comment' => 'QBO ExpenseAccountRef value',
            ])
            ->addColumn('class_qbo_id', 'string', [
                'limit'   => 50,
                'null'    => true,
                'comment' => 'QBO ClassRef value (if item-level class)',
            ])
            ->addColumn('track_qty_on_hand', 'boolean', [
                'default' => false,
            ])
            ->addColumn('qty_on_hand', 'decimal', [
                'precision' => 15,
                'scale'     => 2,
                'null'      => true,
                'comment'   => 'Current quantity (inventory items only)',
            ])
            ->addColumn('status', 'string', [
                'limit'   => 20,
                'default' => 'pending',
                'comment' => 'pending | synced | failed',
            ])
            ->addColumn('qbo_id', 'string', [
                'limit'   => 50,
                'null'    => true,
                'comment' => 'QuickBooks Online Item ID',
            ])
            ->addColumn('sync_token', 'string', [
                'limit'   => 50,
                'null'    => true,
            ])
            ->addColumn('retry_count', 'integer', [
                'default' => 0,
                'signed'  => false,
            ])
            ->addColumn('error_message', 'text', [
                'null' => true,
            ])
            ->addColumn('last_attempt_at', 'datetime', [
                'null' => true,
            ])
            ->addTimestamps()
            ->addIndex(['qbo_id'], [
                'unique' => true,
                'name'   => 'idx_qbo_item_qbo_id',
            ])
            ->addIndex(['status'], [
                'name' => 'idx_qbo_item_status',
            ])
            ->addIndex(['type'], [
                'name' => 'idx_qbo_item_type',
            ])
            ->addIndex(['parent_ref'], [
                'name' => 'idx_qbo_item_parent_ref',
            ])
            ->addForeignKey(
                'qbo_company_id',
                ($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo') . '_companies',
                'id',
                ['delete' => 'CASCADE']
            )
            ->create();
    }
}