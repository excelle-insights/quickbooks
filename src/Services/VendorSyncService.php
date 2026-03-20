<?php

namespace ExcelleInsights\QuickBooks\Services;

use ExcelleInsights\QuickBooks\Client\VendorClient;
use ExcelleInsights\QuickBooks\Repositories\QboVendorRepository;
use ExcelleInsights\QuickBooks\Utils\VendorNameGenerator;
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
        // 1️⃣ Check for existing vendors using multiple criteria
        $existingVendor = $this->findExistingVendor($data);
        
        if ($existingVendor) {
            // Update local record with existing QBO vendor
            $this->vendors->markSynced(
                $this->vendors->create($data), // Create local record first
                $existingVendor->Id,
                $existingVendor->SyncToken ?? '1'
            );

            return (object) [
                'status'   => 'synced',
                'local_id' => $this->vendors->create($data),
                'qbo_id'   => $existingVendor->Id,
                'message'  => 'Vendor already exists in QuickBooks',
                'data'     => $existingVendor,
            ];
        }

        // 2️⃣ Create locally first
        $localId = $this->vendors->create($data);

        try {
            // 3️⃣ Create in QBO
            $response = $this->qbo->create($data);

            if (!isset($response->Vendor)) {
                throw new RuntimeException('Invalid QBO Vendor response');
            }

            // 4️⃣ Mark as synced
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
            // 5️⃣ Handle duplicate name error gracefully
            if (strpos($e->getMessage(), 'Duplicate Name Exists Error') !== false) {
                // Try to find the existing vendor and sync locally
                $existingVendor = $this->findExistingVendorAfterError($data);
                
                if ($existingVendor) {
                    $this->vendors->markSynced(
                        $localId,
                        $existingVendor->Id,
                        $existingVendor->SyncToken ?? '1'
                    );

                    return (object) [
                        'status'   => 'synced',
                        'local_id' => $localId,
                        'qbo_id'   => $existingVendor->Id,
                        'message'  => 'Vendor already existed, now synced locally',
                        'data'     => $existingVendor,
                    ];
                }
                
                // If we can't find existing vendor, try creating with unique name
                try {
                    $uniqueData = $data;
                    $uniqueData['display_name'] = VendorNameGenerator::generateUniqueDisplayName(
                        $data['display_name'],
                        $data['tax_identifier'] ?? null,
                        $data['email'] ?? null
                    );
                    
                    $response = $this->qbo->create($uniqueData);
                    
                    if (isset($response->Vendor)) {
                        $this->vendors->markSynced(
                            $localId,
                            $response->Vendor->Id,
                            $response->Vendor->SyncToken
                        );

                        return (object) [
                            'status'   => 'synced',
                            'local_id' => $localId,
                            'qbo_id'   => $response->Vendor->Id,
                            'message'  => 'Created with unique name: ' . $uniqueData['display_name'],
                            'data'     => $response->Vendor,
                        ];
                    }
                } catch (Throwable $retryError) {
                    error_log('QBO Vendor retry with unique name failed: ' . $retryError->getMessage());
                }
            }

            // 6️⃣ Mark failure, retry later
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

    /**
     * Find existing vendor using multiple criteria (before creation attempt)
     */
    private function findExistingVendor(array $data): ?object
    {
        try {
            $criteria = [];
            
            // Priority 1: Tax Identifier (most reliable)
            if (!empty($data['tax_identifier'])) {
                $criteria['tax_identifier'] = $data['tax_identifier'];
            }
            
            // Priority 2: Email
            if (!empty($data['email'])) {
                $criteria['email'] = $data['email'];
            }
            
            // Priority 3: Display Name (least reliable due to duplicates)
            if (!empty($data['display_name'])) {
                $criteria['display_name'] = $data['display_name'];
            }

            if (empty($criteria)) {
                return null;
            }

            $response = $this->qbo->findPotentialDuplicates($criteria);
            
            if (isset($response->QueryResponse->Vendor) && count($response->QueryResponse->Vendor) > 0) {
                // Return the first match (could be enhanced with better matching logic)
                return $response->QueryResponse->Vendor[0];
            }
            
        } catch (Throwable $e) {
            error_log('Error checking for existing vendor: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Find existing vendor after duplicate name error occurs
     */
    private function findExistingVendorAfterError(array $data): ?object
    {
        try {
            // Try searching by display name first (since that's what caused the error)
            $response = $this->qbo->search($data['display_name']);
            
            if (isset($response->QueryResponse->Vendor) && count($response->QueryResponse->Vendor) > 0) {
                return $response->QueryResponse->Vendor[0];
            }
            
        } catch (Throwable $e) {
            error_log('Error finding vendor after duplicate error: ' . $e->getMessage());
        }

        return null;
    }
}
