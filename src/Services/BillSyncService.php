<?php

namespace ExcelleInsights\QuickBooks\Services;

use ExcelleInsights\QuickBooks\Repositories\QboBillRepository;
use ExcelleInsights\QuickBooks\Repositories\QboBillItemRepository;
use ExcelleInsights\QuickBooks\Client\BillClient;

class BillSyncService
{
    public function __construct(
        private QboBillRepository $bills,
        private QboBillItemRepository $billItems,
        private BillClient $qbo
    ) {}

    /**
     * Create a Bill locally and sync to QBO
     *
     * @param array $data
     *   Required keys:
     *   - qbo_company_id
     *   - vendor_qbo_id
     *   - items (array of bill items)
     *   Optional keys:
     *   - txn_date
     *   - currency
     *
     * @return object Status of sync, local ID, QBO ID (if synced), or error
     */
    public function create(array $data): object
    {
        // 1️⃣ Create locally
        $localId = $this->bills->create($data);

        try {
            // 2️⃣ Sync to QBO
            $response = $this->qbo->create($data);

            if (!isset($response->Bill)) {
                throw new \RuntimeException('Invalid QBO response');
            }

            $qboBill = $response->Bill;

            // 3️⃣ Link local ↔ QBO
            $this->bills->markSynced(
                $localId,
                $qboBill->Id,
                $qboBill->SyncToken
            );

            // 4️⃣ Save line items locally
            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $this->billItems->create([
                        'bill_id'       => $localId,
                        'account_qbo_id'=> $item['account_qbo_id'],
                        'amount'        => $item['amount'],
                        'description'   => $item['description'] ?? null
                    ]);
                }
            }

            return (object)[
                'status'   => 'synced',
                'local_id' => $localId,
                'qbo_id'   => $qboBill->Id,
                'data'     => $qboBill
            ];

        } catch (\Throwable $e) {
            // 5️⃣ Handle errors and mark for retry
            $this->bills->markFailed(
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
