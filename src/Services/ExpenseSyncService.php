<?php

namespace ExcelleInsights\QuickBooks\Services;

use ExcelleInsights\QuickBooks\Repositories\QboExpenseRepository;
use ExcelleInsights\QuickBooks\Repositories\QboExpenseItemRepository;
use ExcelleInsights\QuickBooks\Repositories\QboVendorRepository;
use ExcelleInsights\QuickBooks\Repositories\QboClassRepository;
use ExcelleInsights\QuickBooks\Repositories\QboTaxCodeRepository;
use ExcelleInsights\QuickBooks\Client\ExpenseClient;

class ExpenseSyncService
{
    public function __construct(
        private QboExpenseRepository     $expenses,
        private QboExpenseItemRepository $expenseItems,
        private QboVendorRepository      $vendorRepo,
        private QboClassRepository       $classRepo,
        private QboTaxCodeRepository     $taxCodeRepo,
        private ExpenseClient            $qbo
    ) {}

    /**
     * Create an Expense locally and sync to QBO (Purchase entity).
     *
     * Required keys:
     *   - qbo_company_id
     *   - payment_account_qbo_id  QBO bank/credit account ID
     *   - items (array)
     *       - account_qbo_id   QBO expense account (Category column)
     *       - amount
     *       - description      optional
     *       - qbo_class_id     optional, local qbo_classes.id
     *       - tax_code_id      optional, local qbo_tax_codes.id
     *
     * Optional keys:
     *   - qbo_vendor_id        local qbo_vendors.id (Payee)
     *   - payment_type         Cash | Check | CreditCard  (default: Cash)
     *   - payment_method       free-text payment method label
     *   - txn_date             Y-m-d
     *   - ref_number           Ref no.
     *   - currency
     *   - memo
     */
    public function create(array $data): object
    {
        // 1. Persist locally
        $localId = $this->expenses->create($data);

        // 2. Resolve vendor (Payee) — optional
        if (!empty($data['qbo_vendor_id'])) {
            $vendor = $this->vendorRepo->find((int) $data['qbo_vendor_id']);

            if (!$vendor || !$vendor->qbo_id) {
                return (object)[
                    'status'   => 'queued',
                    'local_id' => $localId,
                    'reason'   => 'Vendor not yet synced to QBO',
                ];
            }

            $data['vendor_qbo_id'] = $vendor->qbo_id;
        }

        // 3. Resolve class + tax code on each line item
        $items = [];
        foreach ($data['items'] ?? [] as $item) {

            if (!empty($item['qbo_class_id'])) {
                $class = $this->classRepo->find((int) $item['qbo_class_id']);

                if (!$class || !$class->qbo_id) {
                    $this->expenses->markFailed(
                        $localId,
                        "Class ID {$item['qbo_class_id']} must be synced before using it in an expense."
                    );
                    return (object)[
                        'status'   => 'failed',
                        'local_id' => $localId,
                        'error'    => "Class ID {$item['qbo_class_id']} must be synced before using it in an expense.",
                    ];
                }

                $item['class_qbo_id'] = $class->qbo_id;
            }

            if (!empty($item['tax_code_id'])) {
                $taxCode = $this->taxCodeRepo->find((int) $item['tax_code_id']);

                if (!$taxCode || !$taxCode->qbo_id) {
                    $this->expenses->markFailed(
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

                if (empty($item['tax_rate']) || (float) $item['tax_rate'] === 0.0) {
                    $item['tax_rate'] = $this->resolveRateFromTaxCode($taxCode);
                }
            }

            // Compute net amount (QBO always wants pre-tax on the line)
            $grossAmount = (float) ($item['amount'] ?? 0);
            $taxType     = $item['tax_type'] ?? 'exclusive';
            $taxRate     = (float) ($item['tax_rate'] ?? 0);

            if (!empty($item['tax_code_qbo_id']) && $taxRate > 0) {
                if ($taxType === 'inclusive') {
                    $netAmount = $grossAmount / (1 + $taxRate / 100);
                    $taxAmount = $grossAmount - $netAmount;
                } else {
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
            // 4. Push to QBO
            $response = $this->qbo->create($data);

            if (!isset($response->Purchase)) {
                throw new \RuntimeException('Invalid QBO response — Purchase object missing.');
            }

            $qboExpense = $response->Purchase;

            // 5. Link local ↔ QBO
            $this->expenses->markSynced($localId, $qboExpense->Id, $qboExpense->SyncToken);

            // 6. Persist line items
            foreach ($data['items'] as $item) {
                $this->expenseItems->create([
                    'expense_id'     => $localId,
                    'account_qbo_id' => $item['account_qbo_id'],
                    'qbo_class_id'   => $item['qbo_class_id'] ?? null,
                    'tax_code_id'    => $item['tax_code_id']  ?? null,
                    'amount'         => $item['amount'],
                    'description'    => $item['description']  ?? null,
                ]);
            }

            return (object)[
                'status'   => 'synced',
                'local_id' => $localId,
                'qbo_id'   => $qboExpense->Id,
                'data'     => $qboExpense,
            ];

        } catch (\Throwable $e) {
            $this->expenses->markFailed($localId, $e->getMessage());

            return (object)[
                'status'   => 'pending',
                'local_id' => $localId,
                'error'    => $e->getMessage(),
            ];
        }
    }

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
            error_log('ExpenseSyncService: could not resolve tax rate — ' . $e->getMessage());
            return 0.0;
        }
    }
}
