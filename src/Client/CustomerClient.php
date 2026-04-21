<?php

namespace ExcelleInsights\QuickBooks\Client;

class CustomerClient extends BaseClient
{
    /**
     * Create a new QuickBooks customer
     */
    public function create(array $data): object
    {
        $payload = array_filter([
            "FullyQualifiedName" => $data['name'] ?? null,
            "PrimaryEmailAddr"   => !empty($data['email'])
                ? ["Address" => $data['email']]
                : null,
            "DisplayName"        => $data['name'] ?? null,
            "GivenName"          => $data['first_name'] ?? null,
            "MiddleName"         => $data['middle_name'] ?? null,
            "FamilyName"         => $data['last_name'] ?? null,
            "Suffix"             => $data['suffix'] ?? null,
            "Title"              => $data['title'] ?? null,
            "Notes"              => $data['notes'] ?? null,
            "PrimaryPhone"       => !empty($data['phone'])
                ? ["FreeFormNumber" => $data['phone']]
                : null,
            "CompanyName"        => $data['company_name'] ?? null,
            "BillAddr"           => array_filter([
                "CountrySubDivisionCode" => $data['country_code'] ?? null,
                "City"                   => $data['city'] ?? null,
                "PostalCode"             => $data['postal_code'] ?? null,
                "Line1"                  => $data['line'] ?? null,
                "Country"                => $data['country'] ?? null
            ], fn($v) => $v !== null && $v !== '') ?: null,
            "ParentRef"  => isset($data['qbo_parent_id']) ? ["value" => $data['qbo_parent_id']] : null,
            "Job"        => isset($data['qbo_parent_id']) ? true : null,
            "PrimaryTaxIdentifier" => $data['kra_pin'] ?? null
        ], fn($v) => $v !== null && $v !== '');

        return $this->sendRequest('POST', $this->endpoint('customer'), $payload);
    }

    /**
     * Retrieve a customer by QuickBooks ID
     */
    public function getById(string $id): object
    {
        return $this->sendRequest('GET', $this->endpoint("customer/" . urlencode($id)));
    }

    /**
     * Search for a customer by FullyQualifiedName
     */
    public function search(string $name): object
    {
        $query = "select Id from Customer Where FullyQualifiedName = '" . trim($name) . "'";
        return $this->sendRequest('GET', $this->endpoint("query?query=" . rawurlencode($query)));
    }

    /**
     * Deactivate a customer
     */
    public function deactivate(string $id, string $syncToken): object
    {
        if (empty($syncToken)) {
            throw new \InvalidArgumentException('syncToken is required to deactivate a customer.');
        }

        $payload = [
            "Id"        => $id,
            "SyncToken" => $syncToken,
            "Active"    => false,
            "sparse"    => true
        ];

        return $this->sendRequest('POST', $this->endpoint('customer'), $payload);
    }

    /**
     * Get a single customer's balance by QuickBooks ID
     */
    public function getBalance(string $id): object
    {
        $query = "SELECT Id, DisplayName, Balance, BalanceWithJobs FROM Customer WHERE Id = '" . $id . "'";
        return $this->sendRequest('GET', $this->endpoint("query?query=" . rawurlencode($query)));
    }

    /**
     * Get all customers with their balances
     */
    public function getAllWithBalances(int $startPosition = 1, int $maxResults = 1000): object
    {
        $query = "SELECT Id, DisplayName, Balance, BalanceWithJobs FROM Customer STARTPOSITION " . $startPosition . " MAXRESULTS " . $maxResults;
        return $this->sendRequest('GET', $this->endpoint("query?query=" . rawurlencode($query)));
    }

    /**
     * Get customers with outstanding balances (balance > 0)
     */
    public function getWithOutstandingBalances(int $startPosition = 1, int $maxResults = 1000): object
    {
        $query = "SELECT Id, GivenName, MiddleName, FamilyName, DisplayName, PrimaryPhone, PrimaryEmailAddr, Balance, BalanceWithJobs, PrimaryTaxIdentifier FROM Customer WHERE Balance > '0' STARTPOSITION " . $startPosition . " MAXRESULTS " . $maxResults;
        return $this->sendRequest('GET', $this->endpoint("query?query=" . rawurlencode($query)));
    }
}
