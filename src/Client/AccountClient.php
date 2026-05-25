<?php

namespace ExcelleInsights\QuickBooks\Client;

class AccountClient extends BaseClient
{
    /**
     * Create a new QuickBooks account
     */
    public function create(array $data): object
    {
        $payload = array_filter([
            "Name"          => $data['name'] ?? null,
            "AccountType"   => $data['account_type'] ?? null, // e.g. Income, Expense
            "AccountSubType" => $data['account_sub_type'] ?? null,
            "Description"   => $data['description'] ?? null,
            "Classification" => $data['classification'] ?? null,
            "CurrencyRef"   => isset($data['currency'])
                ? ["value" => $data['currency']]
                : null,
        ], fn($v) => $v !== null && $v !== '');

        return $this->sendRequest(
            'POST',
            $this->endpoint('account'),
            $payload
        );
    }

    /**
     * Retrieve a account by QuickBooks ID
     */
    public function getById(string $id): object
    {
        return $this->sendRequest('GET', $this->endpoint("account/" . urlencode($id)));
    }

    /**
     * Retrieve all accounts
     */
    public function getAll(): object
    {
        $query = "select * from Account";
        return $this->sendRequest('GET', $this->endpoint("query?query=" . rawurlencode($query)));
    }

    /**
     * Retrieve active Bank and Credit Card accounts from QBO.
     * Used to resolve a payment account for BillPayment without requiring
     * a pre-synced local table.
     */
    public function getBankAccounts(): array
    {
        $query  = "SELECT Id, Name, AccountType, AccountSubType, Active FROM Account "
                . "WHERE AccountType IN ('Bank', 'Credit Card') AND Active = true";
        $result = $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));

        $accounts = $result->QueryResponse->Account ?? [];
        if (!is_array($accounts)) {
            $accounts = empty($accounts) ? [] : [$accounts];
        }
        return $accounts;
    }

    /**
     * Return the best QBO bank account ID to use as a BillPayment source.
     * Prefers Checking, then any Bank account, then Credit Card.
     * Returns null if QBO has no active bank accounts.
     */
    public function resolveBestBankAccountId(): ?string
    {
        $accounts = $this->getBankAccounts();
        if (empty($accounts)) return null;

        $checking = null;
        $bank     = null;
        $card     = null;

        foreach ($accounts as $acct) {
            $type    = $acct->AccountType    ?? '';
            $subtype = $acct->AccountSubType ?? '';
            $id      = $acct->Id             ?? null;
            if (!$id) continue;

            if ($type === 'Bank' && stripos($subtype, 'Checking') !== false && !$checking) {
                $checking = $id;
            } elseif ($type === 'Bank' && !$bank) {
                $bank = $id;
            } elseif ($type === 'Credit Card' && !$card) {
                $card = $id;
            }
        }

        return $checking ?? $bank ?? $card;
    }
}
