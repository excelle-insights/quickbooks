<?php

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
        // Create locally
        $localId = $this->customers->create($data);

        try {
            if (!empty($data['parent_id'])) {
                // Fetch from local parent if provided
                $parent = $this->customers->find($data['parent_id']);
                if ($parent && $parent->qbo_id) {
                    $data['qbo_parent_id'] = $parent->qbo_id;
                }

                if (isset($data['parent_id']) && !isset($data['qbo_parent_id'])) {
                    throw new \InvalidArgumentException('Parent QBO ID is required for QBO sync');
                }
            }

            // Create in QBO
            $response = $this->qbo->create($data);

            if (!isset($response->Customer)) {
                throw new \RuntimeException('Invalid QBO response');
            }

            // Link local ↔ QBO
            $this->customers->markSynced(
                $localId,
                $response->Customer->Id,
                $response->Customer->SyncToken
            );

            return (object)[
                'status'   => 'synced',
                'local_id' => $localId,
                'qbo_id'   => $response->Customer->Id,
                'data'     => $response->Customer
            ];

        } catch (\Throwable $e) {
            error_log("QBO Customer sync failed: " . $e->getMessage());

            // Leave unsynced, retry later
            $this->customers->markFailed(
                $localId,
                $e->getMessage()
            );

            return (object)[
                'status'   => 'pending',
                'local_id' => $localId,
                'error'    => $e->getMessage()
            ];
        }
    }
}
