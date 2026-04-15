<?php

namespace ExcelleInsights\QuickBooks\Services;

use ExcelleInsights\QuickBooks\Repositories\QboInvoiceRepository;
use ExcelleInsights\QuickBooks\Repositories\QboInvoiceItemRepository;
use ExcelleInsights\QuickBooks\Repositories\QboCustomerRepository;
use ExcelleInsights\QuickBooks\Repositories\QboClassRepository;
use ExcelleInsights\QuickBooks\Repositories\QboItemRepository;
use ExcelleInsights\QuickBooks\Client\InvoiceClient;

class InvoiceSyncService
{
    public function __construct(
        private QboInvoiceRepository $invoiceRepo,
        private QboInvoiceItemRepository $invoiceItemRepo,
        private QboCustomerRepository $customerRepo,
        private QboClassRepository $classRepo,
        private QboItemRepository $itemRepo,
        private InvoiceClient $invoiceClient
    ) {}

    /**
     * Create invoice locally and attempt sync with QuickBooks Online
     */
    public function create(array $data): object
    {
        // Insert invoice locally first
        $localId = $this->invoiceRepo->create($data);

        $items = [];

        foreach ($data['items'] ?? [] as $item) {

            $item['qbo_invoice_id'] = $localId;
            $this->invoiceItemRepo->create($item);

            // Resolve class
            if (!empty($item['qbo_class_id'])) {

                $class = $this->classRepo->find($item['qbo_class_id']);
                $item = $this->itemRepo->find($item['qbo_item_id']);

                if ($class && $class->qbo_id) {
                    $item['class_qbo_id'] = $class->qbo_id;
                } else {
                    throw new \RuntimeException(
                        "Class must be synced before using it in invoice."
                    );
                }
            }

            $items[] = $item; 
        }
        $data['items'] = $items;

        echo "Output here: " . json_encode($data) . "\n";

        // Load customer (local source of truth)
        $customer = $this->customerRepo->find(
            (int) $data['qbo_customer_id']
        );

        // If customer not yet synced → stop here
        if (!$customer || !$customer->qbo_id) {
            return (object) [
                'status'   => 'queued',
                'local_id' => $localId,
                'reason'   => 'Customer not yet synced to QBO',
            ];
        }

        // Inject QBO customer ID for API payload
        $data['customer_qbo_id'] = $customer->qbo_id;

        try {
            // Attempt to create invoice in QBO
            $qboInvoice = $this->invoiceClient->create($data);

            // Ensure required fields exist
            $qboId = $qboInvoice->Invoice->Id ?? null;
            $syncToken = $qboInvoice->Invoice->SyncToken ?? null;
            $total = $qboInvoice->Invoice->TotalAmt ?? 0;

            if($qboId) {
                // Mark invoice as synced locally
                $this->invoiceRepo->markSynced(
                    $localId,
                    $qboId,
                    $syncToken,
                    $total
                );
            }

            return (object)[
                'status'   => 'synced',
                'local_id' => $localId,
                'qbo_id'   => $qboId,
            ];
        } catch (\Throwable $e) {
            // 4️⃣ If QBO sync fails, mark as failed for retry later
            error_log("QBO Invoice sync failed: " . $e->getMessage() . ":" . $e->getTraceAsString());
            $this->invoiceRepo->markFailed($localId, $e->getMessage());

            return (object)[
                'status'   => 'failed',
                'local_id' => $localId,
                'error'    => $e->getMessage(),
            ];
        }
    }
    public function getById(int $invoice_id)
    {
        $qboInvoice = $this->invoiceClient->getById($invoice_id);
        return $qboInvoice;
    }
}
