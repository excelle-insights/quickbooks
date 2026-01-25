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
            "PrimaryEmailAddr"   => ["Address" => $data['email'] ?? null],
            "DisplayName"        => $data['name'] ?? null,
            "Suffix"             => $data['suffix'] ?? null,
            "Title"              => $data['title'] ?? null,
            "MiddleName"         => $data['middle_name'] ?? null,
            "Notes"              => $data['notes'] ?? null,
            "FamilyName"         => $data['sur_name'] ?? null,
            "PrimaryPhone"       => ["FreeFormNumber" => $data['phone'] ?? null],
            "CompanyName"        => $data['company_name'] ?? null,
            "BillAddr"           => array_filter([
                "CountrySubDivisionCode" => $data['country_code'] ?? null,
                "City"                   => $data['city'] ?? null,
                "PostalCode"             => $data['postal_code'] ?? null,
                "Line1"                  => $data['line'] ?? null,
                "Country"                => $data['country'] ?? null
            ], fn($v) => $v !== null && $v !== ''),
            "GivenName" => $data['given_name'] ?? null
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
}
