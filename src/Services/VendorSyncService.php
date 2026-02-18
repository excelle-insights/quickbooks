<?php

namespace ExcelleInsights\QuickBooks\Services;

use ExcelleInsights\QuickBooks\Client\VendorClient;
use ExcelleInsights\QuickBooks\Repositories\QboVendorRepository;
use RuntimeException;
use Throwable;

class VendorSyncService
{
    public function __construct(
        private QboVendorRepository $vendors,
        private VendorClient $qbo
    ) {}

    /**
     * Create and sync vendor with QuickBooks
     */
    public function create(array $data): object
    {
        // 1️⃣ Create locally first
        $localId = $this->vendors->create($data);

        try {
            // 2️⃣ Create in QBO
            $response = $this->qbo->create($data);

            if (!isset($response->Vendor)) {
                throw new RuntimeException('Invalid QBO Vendor response');
            }

            // 3️⃣ Mark as synced
            $this->vendors->markSynced(
                $localId,
                $response->Vendor->Id,
                $response->Vendor->SyncToken
            );

            return (object) [
                'status'   => 'synced',
                'local_id' => $localId,
                'qbo_id'   => $response->Vendor->Id,
                'data'     => $response->Vendor,
            ];

        } catch (Throwable $e) {
            // 4️⃣ Mark failure, retry later
            error_log('QBO Vendor sync failed: ' . $e->getMessage());

            $this->vendors->markFailed(
                $localId,
                $e->getMessage()
            );

            return (object) [
                'status'   => 'pending',
                'local_id' => $localId,
                'error'    => $e->getMessage(),
            ];
        }
    }
}
