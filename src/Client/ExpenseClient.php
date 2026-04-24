<?php

namespace ExcelleInsights\QuickBooks\Client;

/**
 * QBO Expense client — maps to the Purchase entity in QuickBooks Online.
 * Used for direct procurement expenses paid from a bank or credit account.
 *
 * QBO Purchase PaymentType values:
 *   Cash       → paid from a bank account (most common for direct procurement)
 *   Check      → paid by cheque
 *   CreditCard → paid from a credit card account
 */
class ExpenseClient extends BaseClient
{
    public function create(array $data): object
    {
        if (empty($data['payment_account_qbo_id'])) {
            throw new \InvalidArgumentException('payment_account_qbo_id is required to create an Expense.');
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new \InvalidArgumentException('Expense items (category lines) are required.');
        }

        $lines         = $this->buildLines($data['items']);
        $globalTaxCalc = $this->resolveGlobalTaxCalculation($data['items']);

        $payload = [
            'PaymentType'          => $data['payment_type'] ?? 'Cash',
            'AccountRef'           => ['value' => $data['payment_account_qbo_id']],
            'TxnDate'              => $data['txn_date'] ?? date('Y-m-d'),
            'GlobalTaxCalculation' => $globalTaxCalc,
            'Line'                 => $lines,
        ];

        // Payee (vendor) — optional on the QBO form
        if (!empty($data['vendor_qbo_id'])) {
            $payload['EntityRef'] = [
                'value' => $data['vendor_qbo_id'],
                'type'  => 'Vendor',
            ];
        }

        if (!empty($data['currency'])) {
            $payload['CurrencyRef'] = ['value' => $data['currency']];
        }

        if (!empty($data['ref_number'])) {
            $payload['DocNumber'] = $data['ref_number'];
        }

        if (!empty($data['payment_method'])) {
            $payload['PaymentMethodRef'] = ['name' => $data['payment_method']];
        }

        if (!empty($data['memo'])) {
            $payload['PrivateNote'] = $data['memo'];
        }

        return $this->sendRequest('POST', $this->endpoint('purchase'), $payload);
    }

    public function getById(string $qboId): object
    {
        return $this->sendRequest('GET', $this->endpoint('purchase/' . urlencode($qboId)));
    }

    public function getAll(int $maxResults = 1000, int $startPosition = 1): object
    {
        $query = "SELECT * FROM Purchase STARTPOSITION $startPosition MAXRESULTS $maxResults";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }

    private function buildLines(array $items): array
    {
        $lines = [];

        foreach ($items as $item) {
            $netAmount = isset($item['net_amount'])
                ? (float) $item['net_amount']
                : (float) ($item['amount'] ?? 0);

            $detail = [
                'AccountRef' => ['value' => $item['account_qbo_id']],
            ];

            if (!empty($item['class_qbo_id'])) {
                $detail['ClassRef'] = ['value' => $item['class_qbo_id']];
            }

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
