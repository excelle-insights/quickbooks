<?php

namespace ExcelleInsights\QuickBooks\Client;

class BillPaymentClient extends BaseClient
{
    /**
     * Create a new BillPayment in QuickBooks.
     *
     * Before posting, each referenced Bill is fetched from QBO to confirm it
     * is still open (Balance > 0). Bills that are already paid, voided, or
     * deleted in QBO cause error 610 "Object Not Found / inactive" — this
     * pre-flight check surfaces a clear message instead.
     *
     * bank_account_qbo_id is optional — if omitted, the client queries QBO
     * directly for the first active Bank (Checking preferred) account.
     */
    public function create(array $data): object
    {
        if (empty($data['vendor_qbo_id'])) {
            throw new \InvalidArgumentException('vendor_qbo_id is required to create a bill payment.');
        }

        if (empty($data['total_amount'])) {
            throw new \InvalidArgumentException('Payment amount is required.');
        }

        if (empty($data['bill_payments']) || !is_array($data['bill_payments'])) {
            throw new \InvalidArgumentException('Bill payments are required.');
        }

        // ── Pre-flight: verify each bill is still open and not already linked ─
        $lineItemData  = [];
        $skippedBills  = [];
        $totalVerified = 0.0;

        foreach ($data['bill_payments'] as $billPayment) {
            if (empty($billPayment['qbo_bill_id']) || empty($billPayment['amount'])) {
                continue;
            }

            $billId = $billPayment['qbo_bill_id'];
            $amount = (float) $billPayment['amount'];

            try {
                $billResponse = $this->sendRequest('GET', $this->endpoint('bill/' . urlencode($billId)));
                $bill         = $billResponse->Bill ?? null;

                if (!$bill) {
                    $skippedBills[] = "Bill $billId: not found in QuickBooks";
                    continue;
                }

                $balance   = (float) ($bill->Balance   ?? 0);
                $totalAmt  = (float) ($bill->TotalAmt  ?? 0);
                $docNumber = $bill->DocNumber ?? $billId;

                if ($balance <= 0) {
                    $skippedBills[] = "Bill $billId ($docNumber): already fully paid in QuickBooks (Balance = 0)";
                    continue;
                }

                // Check for existing linked BillPayments on this bill.
                // Error 620 fires when a bill already has an active payment linked to it.
                $linkedPayments = [];
                if (!empty($bill->LinkedTxn)) {
                    $linked = is_array($bill->LinkedTxn) ? $bill->LinkedTxn : [$bill->LinkedTxn];
                    foreach ($linked as $lt) {
                        if (($lt->TxnType ?? '') === 'BillPayment') {
                            $linkedPayments[] = $lt->TxnId;
                        }
                    }
                }

                if (!empty($linkedPayments)) {
                    // Bill already has a BillPayment linked in QBO.
                    // If balance == 0 it's fully paid — skip entirely.
                    // If balance > 0 but linked, QBO will still reject with 620 — skip and report.
                    $skippedBills[] = "Bill $billId ($docNumber): already has a payment linked in QuickBooks "
                        . "(BillPayment ID(s): " . implode(', ', $linkedPayments) . "). "
                        . "To re-pay this bill, first delete the existing payment in QuickBooks, then sync again.";
                    continue;
                }

                // Cap payment amount to the remaining balance
                $payAmount = min($amount, $balance);

                $lineItemData[]  = [
                    'Amount'    => round($payAmount, 2),
                    'LinkedTxn' => [[
                        'TxnId'   => $billId,
                        'TxnType' => 'Bill',
                    ]],
                ];
                $totalVerified += $payAmount;

            } catch (\Throwable $e) {
                // If the bill fetch itself returns 620/610, surface a clear message
                $msg = $e->getMessage();
                if (strpos($msg, '620') !== false || strpos($msg, 'Cannot Be Linked') !== false) {
                    $skippedBills[] = "Bill $billId: already has a payment linked in QuickBooks (error 620). "
                        . "Delete the existing QBO BillPayment first, then retry.";
                } elseif (strpos($msg, '610') !== false || strpos($msg, 'Object Not Found') !== false) {
                    $skippedBills[] = "Bill $billId: inactive or deleted in QuickBooks (error 610).";
                } else {
                    $skippedBills[] = "Bill $billId: could not verify — $msg";
                }
            }
        }

        if (!empty($skippedBills)) {
            error_log('BillPaymentClient: skipped bills — ' . implode(' | ', $skippedBills));
        }

        if (empty($lineItemData)) {
            // Build a clean user-facing message from the skip reasons
            $reasons = array_map(function($s) {
                // Strip the "Bill 147 (INV-001): " prefix for a cleaner display
                return preg_replace('/^Bill \S+ \([^)]+\): /', '', $s);
            }, $skippedBills);

            $userMsg = 'Cannot sync payment to QuickBooks:';
            foreach ($reasons as $r) {
                $userMsg .= "\n• " . $r;
            }
            if (empty($reasons)) {
                $userMsg .= "\n• No valid open bills found to pay.";
            }
            throw new \RuntimeException($userMsg);
        }

        // Use verified total (may differ from caller's total if some bills were skipped/capped)
        $finalTotal = $totalVerified > 0 ? $totalVerified : (float) $data['total_amount'];

        // ── Resolve bank account ──────────────────────────────────────────────
        // Always fetch live from QBO to avoid using a stale/inactive local ID.
        $bankAcctId = $data['bank_account_qbo_id'] ?? null;

        if (!empty($bankAcctId)) {
            // Caller supplied an ID — verify it still exists and is active in QBO
            try {
                $acctCheck = $this->sendRequest('GET', $this->endpoint('account/' . urlencode($bankAcctId)));
                $acct      = $acctCheck->Account ?? null;
                if (!$acct || empty($acct->Active)) {
                    // Supplied account is inactive — fall through to auto-resolve
                    error_log("BillPaymentClient: supplied bank account $bankAcctId is inactive in QBO — auto-resolving");
                    $bankAcctId = null;
                }
            } catch (\Throwable $e) {
                // Account not found in QBO — fall through to auto-resolve
                error_log("BillPaymentClient: supplied bank account $bankAcctId not found in QBO ({$e->getMessage()}) — auto-resolving");
                $bankAcctId = null;
            }
        }

        if (empty($bankAcctId)) {
            // Auto-resolve: fetch live from QBO, prefer Checking
            $accountClient = new AccountClient(
                $this->baseUrl,
                $this->companyId,
                $this->auth,
                $this->http
            );
            $bankAcctId = $accountClient->resolveBestBankAccountId();
        }

        if (empty($bankAcctId)) {
            throw new \InvalidArgumentException(
                'No active Bank account found in QuickBooks. ' .
                'Please ensure at least one Bank account exists and is active in QBO.'
            );
        }

        // ── Build payload ─────────────────────────────────────────────────────
        // QBO BillPayment supports PayType: 'Check' or 'CreditCard'.
        // EFT / Bank Transfer / MPESA are all sent as 'Check' (bank debit).
        $method    = strtoupper($data['payment_method'] ?? 'CHECK');
        $useCredit = ($method === 'CREDIT_CARD' || $method === 'CREDITCARD');

        $payload = [
            'VendorRef' => ['value' => $data['vendor_qbo_id']],
            'TotalAmt'  => $finalTotal,
            'TxnDate'   => $data['txn_date'] ?? date('Y-m-d'),
            'PayType'   => $useCredit ? 'CreditCard' : 'Check',
            'Line'      => $lineItemData,
        ];

        if ($useCredit) {
            $payload['CreditCardPayment'] = [
                'CCAccountRef' => ['value' => $bankAcctId],
            ];
        } else {
            $payload['CheckPayment'] = [
                'BankAccountRef' => ['value' => $bankAcctId],
            ];
        }

        if (!empty($data['memo'])) {
            $payload['PrivateNote'] = $data['memo'];
        }

        // Log what we're sending for debugging
        $billIds = array_map(function($line) {
            return $line['LinkedTxn'][0]['TxnId'] ?? '?';
        }, $lineItemData);
        error_log('BillPaymentClient: posting payment — vendor=' . $data['vendor_qbo_id']
            . ' bank=' . $bankAcctId
            . ' total=' . $finalTotal
            . ' bills=' . implode(',', $billIds)
        );

        return $this->sendRequest('POST', $this->endpoint('billpayment'), $payload);
    }

    /**
     * Retrieve a bill payment by QBO ID
     */
    public function getById(string $qboBillPaymentId): object
    {
        return $this->sendRequest(
            'GET',
            $this->endpoint('billpayment/' . urlencode($qboBillPaymentId))
        );
    }
}
