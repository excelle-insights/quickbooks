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
}
