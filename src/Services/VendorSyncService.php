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
     * Also provides upsertFromQbo for pull-only flow (dropdown-only, no acc_suppliers row)
     */
    public function create(array $data): object
    {
        // 1️⃣ Check for existing vendors using multiple criteria (QBO side)
        $existingVendor = $this->findExistingVendor($data);
        
        if ($existingVendor) {
            // Vendor already exists in QBO — upsert local cache and link, don't create duplicate local row
            $existingLocal = $this->vendors->findByQboId($existingVendor->Id);
            if ($existingLocal) {
                // Already have local cache for this QBO vendor — just ensure synced
                $this->vendors->markSynced($existingLocal->id, $existingVendor->Id, $existingVendor->SyncToken ?? '1');
                // Also update local display fields from QBO (keep cache fresh)
                $this->vendors->updateFromQboData($existingLocal->id, $existingVendor);
                return (object) [
                    'status'   => 'synced',
                    'local_id' => $existingLocal->id,
                    'qbo_id'   => $existingVendor->Id,
                    'message'  => 'Vendor already exists in QuickBooks',
                    'data'     => $existingVendor,
                ];
            }
            // No local cache yet — create one and link to existing QBO vendor
            $localId = $this->vendors->create($data);
            $this->vendors->markSynced($localId, $existingVendor->Id, $existingVendor->SyncToken ?? '1');

            return (object) [
                'status'   => 'synced',
                'local_id' => $localId,
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
     * Pull a QBO Vendor object into local qbo_vendors cache (dropdown-only, NOT acc_suppliers)
     * Used by pull_qbo_vendors.php after change to not create acc_suppliers rows.
     */
    public function upsertFromQbo(object $qboVendor): object
    {
        // $qboVendor is the raw QBO Vendor (Id, DisplayName, etc.)
        $qboId = $qboVendor->Id ?? null;
        if (!$qboId) throw new \InvalidArgumentException('QBO Vendor Id is required');

        $existing = $this->vendors->findByQboId((string)$qboId);
        if ($existing) {
            $this->vendors->updateFromQboData($existing->id, $qboVendor);
            // Ensure status synced
            $this->vendors->markSynced($existing->id, (string)$qboId, $qboVendor->SyncToken ?? $existing->sync_token ?? '1');
            return (object)['status'=>'synced','local_id'=>$existing->id,'qbo_id'=>$qboId,'action'=>'updated','data'=>$qboVendor];
        }

        // Map QBO fields to local columns
        $data = [
            'qbo_company_id' => 1,
            'display_name'   => $qboVendor->DisplayName ?? '',
            'company_name'   => $qboVendor->CompanyName ?? $qboVendor->DisplayName ?? '',
            'given_name'     => $qboVendor->GivenName ?? null,
            'family_name'    => $qboVendor->FamilyName ?? null,
            'title'          => $qboVendor->Title ?? null,
            'suffix'         => $qboVendor->Suffix ?? null,
            'print_on_check_name' => $qboVendor->PrintOnCheckName ?? null,
            'email'          => $qboVendor->PrimaryEmailAddr->Address ?? null,
            'phone'          => $qboVendor->PrimaryPhone->FreeFormNumber ?? null,
            'mobile'         => $qboVendor->Mobile->FreeFormNumber ?? null,
            'website'        => $qboVendor->WebAddr->URI ?? null,
            'tax_identifier' => $qboVendor->TaxIdentifier ?? null,
            'account_number' => $qboVendor->AcctNum ?? null,
            'bill_addr'      => isset($qboVendor->BillAddr) ? [
                'line1' => $qboVendor->BillAddr->Line1 ?? null,
                'line2' => $qboVendor->BillAddr->Line2 ?? null,
                'line3' => $qboVendor->BillAddr->Line3 ?? null,
                'city'  => $qboVendor->BillAddr->City ?? null,
                'country' => $qboVendor->BillAddr->Country ?? null,
                'state' => $qboVendor->BillAddr->CountrySubDivisionCode ?? null,
                'postal_code' => $qboVendor->BillAddr->PostalCode ?? null,
            ] : null,
        ];

        $localId = $this->vendors->create($data);
        $this->vendors->markSynced($localId, (string)$qboId, $qboVendor->SyncToken ?? '1');

        return (object)['status'=>'synced','local_id'=>$localId,'qbo_id'=>$qboId,'action'=>'imported','data'=>$qboVendor];
    }

    /**
     * Find existing vendor using multiple criteria (before creation attempt)
     */
    private function findExistingVendor(array $data): ?object
    {
        // First check local cache by hash / unique fields to avoid QBO call for known vendors
        $local = $this->vendors->findByUniqueFields($data['display_name'] ?? null, $data['tax_identifier'] ?? null, $data['email'] ?? null);
        if ($local && !empty($local->qbo_id)) {
            // We have a synced local cache — try to fetch full QBO record by that qbo_id to confirm still exists
            try {
                $full = $this->qbo->getById($local->qbo_id);
                if (isset($full->Vendor)) return $full->Vendor;
            } catch (Throwable $e) {
                // Fall through to QBO search
            }
        }

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
