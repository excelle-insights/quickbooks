<?php

namespace ExcelleInsights\QuickBooks\Client;

class CustomerClient extends BaseClient
{
    public function create(array $data): object
    {
        $payload = [
            "FullyQualifiedName" => $data['name'] ?? '',
            "PrimaryEmailAddr" => ["Address" => $data['email'] ?? ''],
            "DisplayName" => $data['name'] ?? '',
            "Suffix" => $data['suffix'] ?? '',
            "Title" => $data['title'] ?? '',
            "MiddleName" => $data['middle_name'] ?? '',
            "Notes" => $data['notes'] ?? '',
            "FamilyName" => $data['sur_name'] ?? '',
            "PrimaryPhone" => ["FreeFormNumber" => $data['phone'] ?? ''],
            "CompanyName" => $data['company_name'] ?? '',
            "BillAddr" => [
                "CountrySubDivisionCode" => $data['country_code'] ?? '',
                "City" => $data['city'] ?? '',
                "PostalCode" => $data['postal_code'] ?? '',
                "Line1" => $data['line'] ?? '',
                "Country" => $data['country'] ?? ''
            ],
            "GivenName" => $data['given_name'] ?? ''
        ];

        return $this->sendRequest('POST', $this->endpoint('customer'), $payload);
    }

    public function getById(string $id): object
    {
        return $this->sendRequest('GET', $this->endpoint("customer/" . urlencode($id)));
    }

    public function search(string $name): object
    {
        $query = "select Id from Customer Where FullyQualifiedName = '" . trim($name) . "'";
        return $this->sendRequest('GET', $this->endpoint("query?query=" . urlencode($query)));
    }

    public function deactivate(string $id, string $syncToken): object
    {
        $payload = [
            "Id" => $id,
            "SyncToken" => $syncToken,
            "Active" => false,
            "sparse" => true
        ];

        return $this->sendRequest('POST', $this->endpoint('customer'), $payload);
    }
}
