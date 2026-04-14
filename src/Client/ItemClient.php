<?php

/**
 * File: src/Client/ItemClient.php
 *
 * QuickBooks Online API client for Item (Product/Service/Category) operations.
 * Handles create, read, update, search, and deactivation of items via the QBO REST API.
 * Follows the same patterns as BillClient and CustomerClient.
 */

namespace ExcelleInsights\QuickBooks\Client;

class ItemClient extends BaseClient
{
    /**
     * Create a new Item in QuickBooks Online
     *
     * Supports: Service, Inventory, NonInventory, Category
     * Sub-items require ParentRef with the parent's QBO ID.
     */
    public function create(array $data): object
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Item name is required.');
        }

        $payload = array_filter([
            'Name'                => $data['name'],
            'Type'                => $data['type'] ?? 'Service',
            'Description'         => $data['description'] ?? null,
            'Active'              => $data['active'] ?? true,
            'Taxable'             => $data['taxable'] ?? false,
            'UnitPrice'           => $data['unit_price'] ?? null,
            'PurchaseCost'        => $data['purchase_cost'] ?? null,
            'PurchaseDesc'        => $data['purchase_description'] ?? null,
            'TrackQtyOnHand'      => $data['track_qty_on_hand'] ?? false,
            'QtyOnHand'           => $data['qty_on_hand'] ?? null,
            'InvStartDate'        => $data['inv_start_date'] ?? null,
            'FullyQualifiedName'  => $data['fully_qualified_name'] ?? null,
            'Sku'                 => $data['sku'] ?? null,
            'SalesTaxIncluded'    => $data['sales_tax_included'] ?? null,
            'PurchaseTaxIncluded' => $data['purchase_tax_included'] ?? null,
            'ParentRef'           => !empty($data['parent_ref'])
                ? ['value' => $data['parent_ref']]
                : null,
            'SubItem'             => !empty($data['parent_ref']) ? true : null,
            'IncomeAccountRef'    => !empty($data['income_account_qbo_id'])
                ? ['value' => $data['income_account_qbo_id']]
                : null,
            'ExpenseAccountRef'   => !empty($data['expense_account_qbo_id'])
                ? ['value' => $data['expense_account_qbo_id']]
                : null,
            'AssetAccountRef'     => !empty($data['asset_account_qbo_id'])
                ? ['value' => $data['asset_account_qbo_id']]
                : null,
            'ClassRef'            => !empty($data['class_qbo_id'])
                ? ['value' => $data['class_qbo_id']]
                : null,
        ], fn($v) => $v !== null && $v !== '');

        return $this->sendRequest('POST', $this->endpoint('item'), $payload);
    }

    /**
     * Update an existing Item in QuickBooks Online (sparse update)
     */
    public function update(string $qboId, string $syncToken, array $data): object
    {
        if (empty($syncToken)) {
            throw new \InvalidArgumentException('syncToken is required to update an Item.');
        }

        $payload = array_filter([
            'Id'                  => $qboId,
            'SyncToken'           => $syncToken,
            'sparse'              => true,
            'Name'                => $data['name'] ?? null,
            'Type'                => $data['type'] ?? null,
            'Description'         => $data['description'] ?? null,
            'Active'              => $data['active'] ?? null,
            'Taxable'             => $data['taxable'] ?? null,
            'UnitPrice'           => $data['unit_price'] ?? null,
            'PurchaseCost'        => $data['purchase_cost'] ?? null,
            'PurchaseDesc'        => $data['purchase_description'] ?? null,
            'TrackQtyOnHand'      => $data['track_qty_on_hand'] ?? null,
            'QtyOnHand'           => $data['qty_on_hand'] ?? null,
            'FullyQualifiedName'  => $data['fully_qualified_name'] ?? null,
            'Sku'                 => $data['sku'] ?? null,
            'ParentRef'           => !empty($data['parent_ref'])
                ? ['value' => $data['parent_ref']]
                : null,
            'SubItem'             => isset($data['parent_ref'])
                ? !empty($data['parent_ref'])
                : null,
            'IncomeAccountRef'    => !empty($data['income_account_qbo_id'])
                ? ['value' => $data['income_account_qbo_id']]
                : null,
            'ExpenseAccountRef'   => !empty($data['expense_account_qbo_id'])
                ? ['value' => $data['expense_account_qbo_id']]
                : null,
            'AssetAccountRef'     => !empty($data['asset_account_qbo_id'])
                ? ['value' => $data['asset_account_qbo_id']]
                : null,
            'ClassRef'            => !empty($data['class_qbo_id'])
                ? ['value' => $data['class_qbo_id']]
                : null,
        ], fn($v) => $v !== null && $v !== '');

        return $this->sendRequest('POST', $this->endpoint('item'), $payload);
    }

    /**
     * Retrieve an Item by QBO ID
     */
    public function getById(string $qboItemId): object
    {
        return $this->sendRequest('GET', $this->endpoint('item/' . urlencode($qboItemId)));
    }

    /**
     * Retrieve all Items from QuickBooks Online
     */
    public function getAll(int $maxResults = 1000, int $startPosition = 1): object
    {
        $query = "SELECT * FROM Item STARTPOSITION $startPosition MAXRESULTS $maxResults";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }

    /**
     * Retrieve all Items of a specific type (e.g. Service, Category, Inventory)
     */
    public function getAllByType(string $type, int $maxResults = 1000, int $startPosition = 1): object
    {
        $query = "SELECT * FROM Item WHERE Type = '$type' STARTPOSITION $startPosition MAXRESULTS $maxResults";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }

    /**
     * Search for an Item by name
     */
    public function searchByName(string $name): object
    {
        $query = "SELECT * FROM Item WHERE Name = '" . trim($name) . "'";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }

    /**
     * Search for an Item by FullyQualifiedName (useful for sub-items)
     */
    public function searchByFullyQualifiedName(string $fqn): object
    {
        $query = "SELECT * FROM Item WHERE FullyQualifiedName = '" . trim($fqn) . "'";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }

    /**
     * Deactivate an Item (soft delete)
     */
    public function deactivate(string $qboId, string $syncToken): object
    {
        if (empty($syncToken)) {
            throw new \InvalidArgumentException('syncToken is required to deactivate an Item.');
        }

        $payload = [
            'Id'        => $qboId,
            'SyncToken' => $syncToken,
            'Active'    => false,
            'sparse'    => true,
        ];

        return $this->sendRequest('POST', $this->endpoint('item'), $payload);
    }
}