<?php

namespace ExcelleInsights\QuickBooks\Services;

use ExcelleInsights\QuickBooks\Repositories\QboBillRepository;
use ExcelleInsights\QuickBooks\Repositories\QboBillItemRepository;
use ExcelleInsights\QuickBooks\Repositories\QboVendorRepository;
use ExcelleInsights\QuickBooks\Repositories\QboClassRepository;
use ExcelleInsights\QuickBooks\Repositories\QboTaxCodeRepository;
use ExcelleInsights\QuickBooks\Client\BillClient;

class BillSyncService
{
    public function __construct(
        private QboBillRepository $bills,
        private QboBillItemRepository $billItems,
        private QboVendorRepository $vendorRepo,
        private QboClassRepository $classRepo,
        private QboTaxCodeRepository $taxCodeRepo,
        private BillClient $qbo
    ) {}

    /**
     * Create a Bill locally and sync to QBO
     *
     * @param array $data
     *   Required keys:
     *   - qbo_company_id
     *   - qbo_vendor_id  (local vendor ID — vendor must be synced first)
     *   - items (array of bill line items)
     *     Each item:
     *       - account_qbo_id  (QBO expense account ID)
     *       - amount
     *       - description     (optional)
     *       - qbo_class_id    (optional, local class ID — class must be synced first)
     *       - tax_code_id     (optional, local tax code ID from qbo_tax_codes)
     *   Optional keys:
     *   - txn_date
     *   - currency
     *
     * @return object status, local_id, qbo_id (if synced), or error
     */
    public function create(array $data): object
    {
        // 1. Create locally
        $localId = $this->bills->create($data);

        // 2. Resolve vendor from local DB
        $vendor = $this->vendorRepo->find((int) $data['qbo_vendor_id']);

        if (!$vendor || !$vendor->qbo_id) {
            return (object)[
                'status'   => 'queued',
                'local_id' => $localId,
                'reason'   => 'Vendor not yet synced to QBO',
            ];
        }

        $data['vendor_qbo_id'] = $vendor->qbo_id;

        // 3. Resolve class and tax code on each item
        $items = [];
        foreach ($data['items'] ?? [] as $item) {

            // Resolve class
            if (!empty($item['qbo_class_id'])) {
                $class = $this->classRepo->find((int) $item['qbo_class_id']);

                if (!$class || !$class->qbo_id) {
                    $this->bills->markFailed(
                        $localId,
                        "Class ID {$item['qbo_class_id']} must be synced before using it in a bill."
                    );
                    return (object)[
                        'status'   => 'failed',
                        'local_id' => $localId,
                        'error'    => "Class ID {$item['qbo_class_id']} must be synced before using it in a bill.",
                    ];
                }

                $item['class_qbo_id'] = $class->qbo_id;
            }

            // Resolve tax code
            if (!empty($item['tax_code_id'])) {
                $taxCode = $this->taxCodeRepo->find((int) $item['tax_code_id']);

                if (!$taxCode || !$taxCode->qbo_id) {
                    $this->bills->markFailed(
                        $localId,
                        "Tax code ID {$item['tax_code_id']} not found. Run syncTaxCodes() first."
                    );
                    return (object)[
                        'status'   => 'failed',
                        'local_id' => $localId,
                        'error'    => "Tax code ID {$item['tax_code_id']} not found. Run syncTaxCodes() first.",
                    ];
                }

                $item['tax_code_qbo_id'] = $taxCode->qbo_id;

                // If caller didn't pass a rate (or passed 0), pull it from the linked
                // tax_types row so QBO receives the correct tax calculation
                if (empty($item['tax_rate']) || (float)$item['tax_rate'] === 0.0) {
                    $item['tax_rate'] = $this->resolveRateFromTaxCode($taxCode);
                }
            }

            // Compute net_amount and tax_amount for QBO payload.
            // QBO always wants the NET (pre-tax) amount on the line.
            // tax_type and tax_rate come from the item (set by bulk_sync_bills).
            $grossAmount = (float) ($item['amount'] ?? 0);
            $taxType     = $item['tax_type'] ?? 'exclusive';
            $taxRate     = (float) ($item['tax_rate'] ?? 0);

            if (!empty($item['tax_code_qbo_id']) && $taxRate > 0) {
                if ($taxType === 'inclusive') {
                    // Gross includes tax — extract net
                    $netAmount = $grossAmount / (1 + $taxRate / 100);
                    $taxAmount = $grossAmount - $netAmount;
                } else {
                    // Exclusive — net = gross, tax is on top
                    $netAmount = $grossAmount;
                    $taxAmount = $grossAmount * ($taxRate / 100);
                }
            } else {
                $netAmount = $grossAmount;
                $taxAmount = 0.0;
            }

            $item['net_amount'] = round($netAmount, 2);
            $item['tax_amount'] = round($taxAmount, 2);

            $items[] = $item;
        }

        $data['items'] = $items;

        try {
            // 4. Sync to QBO
            $response = $this->qbo->create($data);

            if (!isset($response->Bill)) {
                throw new \RuntimeException('Invalid QBO response');
            }

            $qboBill = $response->Bill;

            // 5. Link local ↔ QBO
            $this->bills->markSynced(
                $localId,
                $qboBill->Id,
                $qboBill->SyncToken
            );

            // 6. Save line items locally
            foreach ($data['items'] as $item) {
                $this->billItems->create([
                    'bill_id'        => $localId,
                    'qbo_class_id'   => $item['qbo_class_id'] ?? null,
                    'tax_code_id'    => $item['tax_code_id'] ?? null,
                    'account_qbo_id' => $item['account_qbo_id'],
                    'amount'         => $item['amount'],
                    'description'    => $item['description'] ?? null,
                ]);
            }

            return (object)[
                'status'   => 'synced',
                'local_id' => $localId,
                'qbo_id'   => $qboBill->Id,
                'data'     => $qboBill,
            ];
        } catch (\Throwable $e) {
            $this->bills->markFailed($localId, $e->getMessage());

            return (object)[
                'status'   => 'pending',
                'local_id' => $localId,
                'error'    => $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve the tax rate for a qbo_tax_codes row.
     * Looks up the linked tax_types row via local_tax_id and returns its tax_rate.
     * Falls back to 0 if no link or no rate found.
     */
    private function resolveRateFromTaxCode(object $taxCode): float
    {
        if (empty($taxCode->local_tax_id)) {
            return 0.0;
        }

        try {
            $pdo  = $this->taxCodeRepo->getPdo();
            $stmt = $pdo->prepare("SELECT tax_rate FROM tax_types WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $taxCode->local_tax_id]);
            $row = $stmt->fetch(\PDO::FETCH_OBJ);
            return $row ? (float) $row->tax_rate : 0.0;
        } catch (\Throwable $e) {
            error_log('BillSyncService: could not resolve tax rate — ' . $e->getMessage());
            return 0.0;
        }
    }
}
