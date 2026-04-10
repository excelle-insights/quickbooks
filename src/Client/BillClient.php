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
