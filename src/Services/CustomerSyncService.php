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
        // 1️⃣ Create locally
        $localId = $this->customers->create($data);

        try {
            // 2️⃣ Create in QBO
            $response = $this->qbo->create($data);

            if (!isset($response->Customer)) {
                throw new \RuntimeException('Invalid QBO response');
            }

            // 3️⃣ Link local ↔ QBO
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
            // 4️⃣ Leave unsynced, retry later
            return (object)[
                'status'   => 'pending',
                'local_id' => $localId,
                'error'    => $e->getMessage()
            ];
        }
    }
}
