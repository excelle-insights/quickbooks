<?php

namespace ExcelleInsights\QuickBooks\Services;

use ExcelleInsights\QuickBooks\Repositories\QboInvoiceRepository;
use ExcelleInsights\QuickBooks\Client\InvoiceClient;

class InvoiceSyncService
{
    public function __construct(
        private QboInvoiceRepository $invoiceRepo,
        private InvoiceClient $invoiceClient
    ) {}

    /**
     * Create invoice locally and attempt sync with QuickBooks Online
     */
    public function create(array $data): object
    {
        // 1️⃣ Insert invoice locally first
        $localId = $this->invoiceRepo->create($data);

        try {
            // 2️⃣ Attempt to create invoice in QBO
            $qboInvoice = $this->invoiceClient->create($data);

            // Ensure required fields exist
            $qboId = $qboInvoice->Id ?? null;
            $syncToken = $qboInvoice->SyncToken ?? null;
            $total = $qboInvoice->TotalAmt ?? 0;

            // 3️⃣ Mark invoice as synced locally
            $this->invoiceRepo->markSynced(
                $localId,
                $qboId,
                $syncToken,
                $total
            );

            return (object)[
                'status'   => 'synced',
                'local_id' => $localId,
                'qbo_id'   => $qboId,
            ];
        } catch (\Throwable $e) {
            // 4️⃣ If QBO sync fails, mark as failed for retry later
            error_log("QBO Invoice sync failed: " . $e->getMessage . ":" . $e->getTraceAsString());
            $this->invoiceRepo->markFailed($localId, $e->getMessage());

            return (object)[
                'status'   => 'failed',
                'local_id' => $localId,
                'error'    => $e->getMessage(),
            ];
        }
    }
}
