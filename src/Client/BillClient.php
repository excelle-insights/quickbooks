<?php

namespace ExcelleInsights\QuickBooks\Client;

use ExcelleInsights\QuickBooks\Auth\Authentication;

class BillClient extends BaseClient
{
    /**
     * Create a new Bill in QuickBooks Online
     *
     * For non-US locales (e.g. Kenya VAT):
     *  - Each taxable line needs TaxCodeRef in AccountBasedExpenseLineDetail
     *  - The bill needs TxnTaxDetail with the total tax amount so QBO shows tax
     *  - For inclusive tax: Amount on line = net (pre-tax), GlobalTaxCalculation = TaxInclusive
     *  - For exclusive tax: Amount on line = net, GlobalTaxCalculation = TaxExcluded
     */
    public function create(array $data): object
    {
        if (empty($data['vendor_qbo_id'])) {
            throw new \InvalidArgumentException('vendor_qbo_id is required to create a Bill.');
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new \InvalidArgumentException('Bill items are required.');
        }

        // Pre-flight: verify the vendor exists and is active in QBO
        // Error 2500 "Names element id X not found" fires when the vendor is inactive/deleted
        try {
            $vendorResp = $this->sendRequest('GET', $this->endpoint('vendor/' . urlencode($data['vendor_qbo_id'])));
            $vendor     = $vendorResp->Vendor ?? null;
            if (!$vendor) {
                throw new \RuntimeException(
                    "Vendor (QBO ID {$data['vendor_qbo_id']}) was not found in QuickBooks. "
                    . "Please re-sync the supplier from the Suppliers list and try again."
                );
            }
            if (isset($vendor->Active) && $vendor->Active === false) {
                throw new \RuntimeException(
                    "Vendor \"{$vendor->DisplayName}\" (QBO ID {$data['vendor_qbo_id']}) is inactive in QuickBooks. "
                    . "Please reactivate the vendor in QBO, then re-sync the supplier and try again."
                );
            }
        } catch (\RuntimeException $e) {
            throw $e; // re-throw our own messages
        } catch (\Throwable $e) {
            // If the vendor fetch itself fails with a QBO error, surface it clearly
            $msg = $e->getMessage();
            if (strpos($msg, 'Names element') !== false || strpos($msg, '2500') !== false) {
                throw new \RuntimeException(
                    "Vendor (QBO ID {$data['vendor_qbo_id']}) is inactive or deleted in QuickBooks. "
                    . "Please reactivate the vendor in QBO, then re-sync the supplier and try again."
                );
            }
            // Non-vendor errors (network, auth) — let them propagate naturally
            throw $e;
        }

        $lines         = $this->buildLines($data['items']);
        $globalTaxCalc = $this->resolveGlobalTaxCalculation($data['items']);

        // For non-US QBO (e.g. Kenya VAT):
        //   - TaxCodeRef on each line tells QBO which tax rate to apply
        //   - GlobalTaxCalculation = TaxExcluded means line Amount is net (pre-tax)
        //   - QBO auto-calculates TxnTaxDetail — do NOT send it manually,
        //     sending a hand-built TxnTaxDetail causes "error while calculating tax" (6000)
        $payload = [
            'VendorRef'            => ['value' => $data['vendor_qbo_id']],
            'TxnDate'              => $data['txn_date'] ?? date('Y-m-d'),
            'GlobalTaxCalculation' => $globalTaxCalc,
            'Line'                 => $lines,
        ];

        if (!empty($data['currency'])) {
            $payload['CurrencyRef'] = ['value' => $data['currency']];
        }

        if (!empty($data['doc_number'])) {
            $payload['DocNumber'] = $data['doc_number'];
        }

        if (!empty($data['due_date'])) {
            $payload['DueDate'] = $data['due_date'];
        }

        if (!empty($data['memo'])) {
            $payload['PrivateNote'] = $data['memo'];
        }

        return $this->sendRequest('POST', $this->endpoint('bill'), $payload);
    }

    /**
     * Retrieve a Bill by QBO ID
     */
    public function getById(string $qboBillId): object
    {
        return $this->sendRequest('GET', $this->endpoint('bill/' . urlencode($qboBillId)));
    }

    /**
     * Retrieve all Bills from QuickBooks
     */
    public function getAll(int $maxResults = 1000, int $startPosition = 1): object
    {
        $query = "SELECT * FROM Bill STARTPOSITION $startPosition MAXRESULTS $maxResults";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }

    /**
     * Run an arbitrary QBO query (e.g. to find a bill by DocNumber)
     */
    public function query(string $query): object
    {
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }

    /**
     * Sparse-update a Bill's DocNumber in QuickBooks Online.
     * Fetches the current SyncToken first, then patches only the DocNumber field.
     */
    public function updateDocNumber(string $qboBillId, string $newDocNumber): object
    {
        // Fetch current bill to get SyncToken (required for any QBO update)
        $current = $this->sendRequest('GET', $this->endpoint('bill/' . urlencode($qboBillId)));
        $bill    = $current->Bill ?? null;

        if (!$bill) {
            throw new \RuntimeException("Bill (QBO ID $qboBillId) not found in QuickBooks.");
        }

        $syncToken = $bill->SyncToken ?? '0';

        $payload = [
            'Id'        => $qboBillId,
            'SyncToken' => $syncToken,
            'sparse'    => true,
            'DocNumber' => $newDocNumber,
        ];

        return $this->sendRequest('POST', $this->endpoint('bill'), $payload);
    }

    /**
     * Void or delete a Bill
     */
    public function void(string $qboBillId, string $syncToken): object
    {
        if (empty($syncToken)) {
            throw new \InvalidArgumentException('syncToken is required to void a Bill.');
        }

        $payload = [
            'Id'        => $qboBillId,
            'SyncToken' => $syncToken,
            'sparse'    => true,
            'PrivateNote' => 'Voided locally'
        ];

        return $this->sendRequest('POST', $this->endpoint('bill'), $payload);
    }

    /**
     * Build QBO line items from local bill items.
     * Amount sent to QBO is always the NET (pre-tax) amount.
     * For inclusive tax the caller must pass net_amount; for exclusive, amount = net.
     */
    private function buildLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            // Use net_amount if provided (pre-calculated for inclusive tax),
            // otherwise fall back to amount
            $netAmount = isset($item['net_amount'])
                ? (float) $item['net_amount']
                : (float) ($item['amount'] ?? 0);

            $detail = [
                'AccountRef' => ['value' => $item['account_qbo_id']],
            ];

            if (!empty($item['class_qbo_id'])) {
                $detail['ClassRef'] = ['value' => $item['class_qbo_id']];
            }

            // TaxCodeRef on the line — required for QBO non-US to tick the Tax checkbox
            if (!empty($item['tax_code_qbo_id'])) {
                $detail['TaxCodeRef'] = ['value' => $item['tax_code_qbo_id']];
            }

            $line = [
                'DetailType'                    => 'AccountBasedExpenseLineDetail',
                'Amount'                        => $netAmount,
                'AccountBasedExpenseLineDetail' => $detail,
            ];

            if (!empty($item['description'])) {
                $line['Description'] = $item['description'];
            }

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * Determine GlobalTaxCalculation for Bills.
     *
     * QBO only accepts TaxExcluded or NotApplicable on purchase transactions (Bills).
     * TaxInclusive is NOT supported for Bills — it causes a 6000 validation error.
     *
     * We always send TaxExcluded when tax is present. The net (pre-tax) amount is
     * already computed by BillSyncService before reaching here, so QBO calculates
     * tax correctly on top of the net line amount.
     */
    private function resolveGlobalTaxCalculation(array $items): string
    {
        foreach ($items as $item) {
            if (!empty($item['tax_code_qbo_id'])) {
                return 'TaxExcluded';
            }
        }
        return 'NotApplicable';
    }
}
