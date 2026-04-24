<?php

/**
 * File: src/Services/CustomerSyncService.php
 *
 * Service layer for QuickBooks Online Customer operations.
 * Handles creating customers locally then syncing to QBO.
 * Before creating in QBO, checks if the customer already exists
 * by DisplayName — if found, links the existing QBO record instead.
 */

namespace ExcelleInsights\QuickBooks\Services;

use ExcelleInsights\QuickBooks\Client\CustomerClient;
use ExcelleInsights\QuickBooks\Repositories\QboCustomerRepository;

class CustomerSyncService
{
    public function __construct(
        private QboCustomerRepository $customers,
        private CustomerClient $qbo
    ) {}

    public function create(array $data): object
    {
        // 1. Create locally
        $localId = $this->customers->create($data);

        try {
            // 2. Resolve parent ref if provided
            if (!empty($data['parent_id'])) {
                $parent = $this->customers->find($data['parent_id']);

                if ($parent && $parent->qbo_id) {
                    $data['qbo_parent_id'] = $parent->qbo_id;
                }

                if (isset($data['parent_id']) && !isset($data['qbo_parent_id'])) {
                    throw new \InvalidArgumentException('Parent QBO ID is required for QBO sync');
                }
            }

            // 3. Check if customer already exists in QBO by DisplayName
            $displayName = $data['name'] ?? null;

            if (!empty($displayName)) {
                $existing = $this->findExistingInQbo($displayName);

                if ($existing) {
                    // Customer already exists in QBO — link locally instead of creating
                    $this->customers->markSynced(
                        $localId,
                        $existing->Id,
                        $existing->SyncToken
                    );

                    return (object)[
                        'status'   => 'synced',
                        'local_id' => $localId,
                        'qbo_id'   => $existing->Id,
                        'source'   => 'existing',
                        'data'     => $existing,
                    ];
                }
            }

            // 4. Customer not found in QBO — create new
            $response = $this->qbo->create($data);

            if (!isset($response->Customer)) {
                throw new \RuntimeException('Invalid QBO response');
            }

            // 5. Link local ↔ QBO
            $this->customers->markSynced(
                $localId,
                $response->Customer->Id,
                $response->Customer->SyncToken
            );

            return (object)[
                'status'   => 'synced',
                'local_id' => $localId,
                'qbo_id'   => $response->Customer->Id,
                'source'   => 'created',
                'data'     => $response->Customer,
            ];

        } catch (\Throwable $e) {
            error_log("QBO Customer sync failed: " . $e->getMessage());

            $this->customers->markFailed(
                $localId,
                $e->getMessage()
            );

            return (object)[
                'status'   => 'pending',
                'local_id' => $localId,
                'error'    => $e->getMessage(),
            ];
        }
    }

    /**
     * Search QBO for an existing customer by DisplayName.
     * Returns the QBO Customer object if found, null otherwise.
     */
    private function findExistingInQbo(string $displayName): ?object
    {
        try {
            $result = $this->qbo->search($displayName);

            if (!empty($result->QueryResponse->Customer)) {
                $customers = $result->QueryResponse->Customer;
                $match = is_array($customers) ? $customers[0] : $customers;

                // The search query uses select Id, so fetch the full record
                if (isset($match->Id)) {
                    $full = $this->qbo->getById($match->Id);
                    return $full->Customer ?? null;
                }
            }
        } catch (\Throwable $e) {
            // Search failed — log and fall through to create
            error_log("QBO Customer search failed: " . $e->getMessage());
        }

        return null;
    }
}