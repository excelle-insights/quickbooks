<?php

namespace ExcelleInsights\QuickBooks\Services;

/**
 * File: src/Services/ItemSyncService.php
 *
 * Service layer for QuickBooks Online Item operations.
 * Handles creating items locally then syncing to QBO, and pulling
 * items from QBO into the local database.
 * Follows the same patterns as BillSyncService.
 */

use ExcelleInsights\QuickBooks\Repositories\QboItemRepository;
use ExcelleInsights\QuickBooks\Repositories\QboClassRepository;
use ExcelleInsights\QuickBooks\Client\ItemClient;

class ItemSyncService
{
    public function __construct(
        private QboItemRepository $items,
        private QboClassRepository $classRepo,
        private ItemClient $qbo
    ) {}

    /**
     * Create an Item locally and sync to QBO
     *
     * @param array $data
     *   Required keys:
     *   - qbo_company_id
     *   - name
     *   Optional keys:
     *   - type              (Service|Inventory|NonInventory|Category, default: Service)
     *   - description
     *   - active            (default: true)
     *   - taxable           (default: false)
     *   - unit_price
     *   - purchase_cost
     *   - purchase_description
     *   - income_account_qbo_id   (QBO account ID — required for Service/NonInventory)
     *   - expense_account_qbo_id  (QBO account ID)
     *   - asset_account_qbo_id    (QBO account ID — required for Inventory)
     *   - qbo_class_id            (local class ID — resolved to QBO ID)
     *   - parent_ref              (QBO ID of parent item, for sub-items)
     *   - track_qty_on_hand       (required true for Inventory)
     *   - qty_on_hand             (required for Inventory)
     *   - inv_start_date          (required for Inventory)
     *   - sku
     *   - currency
     *
     * @return object status, local_id, qbo_id (if synced), or error
     */
    public function create(array $data): object
    {
        // 1. Create locally
        $localId = $this->items->create($data);

        // 2. Resolve class from local DB if provided
        if (!empty($data['qbo_class_id'])) {
            $class = $this->classRepo->find((int) $data['qbo_class_id']);

            if (!$class || !$class->qbo_id) {
                $this->items->markFailed(
                    $localId,
                    "Class ID {$data['qbo_class_id']} must be synced before using it in an item."
                );
                return (object)[
                    'status'   => 'failed',
                    'local_id' => $localId,
                    'error'    => "Class ID {$data['qbo_class_id']} must be synced before using it in an item.",
                ];
            }

            $data['class_qbo_id'] = $class->qbo_id;
        }

        // 3. Validate type-specific requirements
        $type = $data['type'] ?? 'Service';

        if (in_array($type, ['Service', 'NonInventory']) && empty($data['income_account_qbo_id'])) {
            $this->items->markFailed($localId, "income_account_qbo_id is required for $type items.");
            return (object)[
                'status'   => 'failed',
                'local_id' => $localId,
                'error'    => "income_account_qbo_id is required for $type items.",
            ];
        }

        if ($type === 'Inventory') {
            if (empty($data['income_account_qbo_id'])) {
                $this->items->markFailed($localId, 'income_account_qbo_id is required for Inventory items.');
                return (object)[
                    'status'   => 'failed',
                    'local_id' => $localId,
                    'error'    => 'income_account_qbo_id is required for Inventory items.',
                ];
            }
            if (empty($data['asset_account_qbo_id'])) {
                $this->items->markFailed($localId, 'asset_account_qbo_id is required for Inventory items.');
                return (object)[
                    'status'   => 'failed',
                    'local_id' => $localId,
                    'error'    => 'asset_account_qbo_id is required for Inventory items.',
                ];
            }
            if (empty($data['expense_account_qbo_id'])) {
                $this->items->markFailed($localId, 'expense_account_qbo_id is required for Inventory items.');
                return (object)[
                    'status'   => 'failed',
                    'local_id' => $localId,
                    'error'    => 'expense_account_qbo_id is required for Inventory items.',
                ];
            }

            // QBO requires these for inventory
            $data['track_qty_on_hand'] = true;
            $data['qty_on_hand']       = $data['qty_on_hand'] ?? 0;
            $data['inv_start_date']    = $data['inv_start_date'] ?? date('Y-m-d');
        }

        try {
            // 4. Sync to QBO
            $response = $this->qbo->create($data);

            if (!isset($response->Item)) {
                throw new \RuntimeException('Invalid QBO response — missing Item key.');
            }

            $qboItem = $response->Item;

            // 5. Link local ↔ QBO
            $this->items->markSynced(
                $localId,
                $qboItem->Id,
                $qboItem->SyncToken
            );

            return (object)[
                'status'   => 'synced',
                'local_id' => $localId,
                'qbo_id'   => $qboItem->Id,
                'data'     => $qboItem,
            ];
        } catch (\Throwable $e) {
            $this->items->markFailed($localId, $e->getMessage());

            return (object)[
                'status'   => 'pending',
                'local_id' => $localId,
                'error'    => $e->getMessage(),
            ];
        }
    }

    /**
     * Pull all Items from QBO and upsert into the local database.
     * Similar to syncTaxCodes() — run periodically or during setup.
     *
     * @param int $qboCompanyId  Local company ID to associate items with
     * @return array             Summary with counts and per-item results
     */
    public function sync(int $qboCompanyId): array
    {
        $results      = [];
        $successCount = 0;
        $errorCount   = 0;
        $startPosition = 1;
        $maxResults    = 1000;

        try {
            // Paginate through all QBO items
            do {
                $response = $this->qbo->getAll($maxResults, $startPosition);

                $items = $response->QueryResponse->Item ?? [];
                $fetched = count($items);

                foreach ($items as $qboItem) {
                    try {
                        $this->items->upsertFromQbo($qboCompanyId, [
                            'qbo_id'                 => $qboItem->Id,
                            'name'                   => $qboItem->Name,
                            'type'                   => $qboItem->Type,
                            'description'            => $qboItem->Description ?? null,
                            'active'                 => $qboItem->Active ?? true,
                            'sub_item'               => $qboItem->SubItem ?? false,
                            'parent_ref'             => $qboItem->ParentRef->value ?? null,
                            'level'                  => $qboItem->Level ?? 0,
                            'fully_qualified_name'   => $qboItem->FullyQualifiedName ?? null,
                            'taxable'                => $qboItem->Taxable ?? false,
                            'unit_price'             => $qboItem->UnitPrice ?? 0,
                            'purchase_cost'          => $qboItem->PurchaseCost ?? 0,
                            'income_account_qbo_id'  => $qboItem->IncomeAccountRef->value ?? null,
                            'expense_account_qbo_id' => $qboItem->ExpenseAccountRef->value ?? null,
                            'class_qbo_id'           => $qboItem->ClassRef->value ?? null,
                            'track_qty_on_hand'      => $qboItem->TrackQtyOnHand ?? false,
                            'qty_on_hand'            => $qboItem->QtyOnHand ?? null,
                            'sync_token'             => $qboItem->SyncToken ?? '0',
                        ]);

                        $results[] = [
                            'qbo_id'  => $qboItem->Id,
                            'name'    => $qboItem->Name,
                            'type'    => $qboItem->Type,
                            'action'  => 'upserted',
                            'success' => true,
                        ];

                        $successCount++;
                    } catch (\Throwable $e) {
                        $results[] = [
                            'qbo_id'  => $qboItem->Id ?? 'unknown',
                            'name'    => $qboItem->Name ?? 'unknown',
                            'type'    => $qboItem->Type ?? 'unknown',
                            'action'  => 'failed',
                            'success' => false,
                            'error'   => $e->getMessage(),
                        ];

                        $errorCount++;
                    }
                }

                $startPosition += $maxResults;
            } while ($fetched === $maxResults);

            return [
                'success' => true,
                'message' => "Item sync complete. Success: $successCount, Errors: $errorCount",
                'results' => $results,
                'summary' => [
                    'total'   => count($results),
                    'success' => $successCount,
                    'errors'  => $errorCount,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Item sync failed: ' . $e->getMessage(),
                'results' => $results,
            ];
        }
    }
}