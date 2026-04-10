<?php

namespace ExcelleInsights\QuickBooks\Client;

use InvalidArgumentException;

class VendorClient extends BaseClient
{
    /**
     * Create a Vendor in QuickBooks
     */
    public function create(array $data): object
    {
        if (empty($data['display_name'])) {
            throw new InvalidArgumentException(
                'display_name is required to create a vendor.'
            );
        }

        $payload = array_filter([
            'DisplayName' => $data['display_name'],

            'GivenName'   => $data['given_name'] ?? null,
            'FamilyName'  => $data['family_name'] ?? null,
            'Title'       => $data['title'] ?? null,
            'Suffix'      => $data['suffix'] ?? null,

            'CompanyName' => $data['company_name'] ?? null,
            'PrintOnCheckName' => $data['print_on_check_name'] ?? null,

            'PrimaryEmailAddr' => !empty($data['email'])
                ? ['Address' => $data['email']]
                : null,

            'PrimaryPhone' => !empty($data['phone'])
                ? ['FreeFormNumber' => $data['phone']]
                : null,

            'Mobile' => !empty($data['mobile'])
                ? ['FreeFormNumber' => $data['mobile']]
                : null,

            'WebAddr' => !empty($data['website'])
                ? ['URI' => $data['website']]
                : null,

            'TaxIdentifier' => $data['tax_identifier'] ?? null,
            'AcctNum'       => $data['account_number'] ?? null,

            'BillAddr' => $this->buildBillAddress($data['bill_addr'] ?? null),
        ], fn ($v) => $v !== null);

        return $this->sendRequest(
            'POST',
            $this->endpoint('vendor'),
            $payload
        );
    }

    /**
     * Retrieve vendor by QBO ID
     */
    public function getById(string $qboVendorId): object
    {
        return $this->sendRequest(
            'GET',
            $this->endpoint('vendor/' . urlencode($qboVendorId))
        );
    }

    /**
     * Search vendor by DisplayName
     */
    public function search(string $displayName): object
    {
        $query = sprintf(
            "select Id from Vendor where DisplayName = '%s'",
            addslashes(trim($displayName))
        );

        return $this->sendRequest(
            'GET',
            $this->endpoint('query?query=' . rawurlencode($query))
        );
    }

    /**
     * Search vendor by Tax Identifier (Company PIN)
     */
    public function searchByTaxId(string $taxId): object
    {
        $query = sprintf(
            "select Id, DisplayName from Vendor where TaxIdentifier = '%s'",
            addslashes(trim($taxId))
        );

        return $this->sendRequest(
            'GET',
            $this->endpoint('query?query=' . rawurlencode($query))
        );
    }

    /**
     * Search vendor by Email
     */
    public function searchByEmail(string $email): object
    {
        $query = sprintf(
            "select Id, DisplayName from Vendor where PrimaryEmailAddr = '%s'",
            addslashes(trim($email))
        );

        return $this->sendRequest(
            'GET',
            $this->endpoint('query?query=' . rawurlencode($query))
        );
    }

    /**
     * Advanced search for potential duplicates using multiple criteria
     */
    public function findPotentialDuplicates(array $criteria): object
    {
        $conditions = [];
        
        if (!empty($criteria['tax_identifier'])) {
            $conditions[] = sprintf("TaxIdentifier = '%s'", addslashes(trim($criteria['tax_identifier'])));
        }
        
        if (!empty($criteria['email'])) {
            $conditions[] = sprintf("PrimaryEmailAddr = '%s'", addslashes(trim($criteria['email'])));
        }
        
        if (!empty($criteria['display_name'])) {
            $conditions[] = sprintf("DisplayName = '%s'", addslashes(trim($criteria['display_name'])));
        }

        if (empty($conditions)) {
            throw new InvalidArgumentException('At least one search criteria must be provided');
        }

        $query = "select Id, DisplayName, TaxIdentifier, PrimaryEmailAddr from Vendor where " . implode(' OR ', $conditions);

        return $this->sendRequest(
            'GET',
            $this->endpoint('query?query=' . rawurlencode($query))
        );
    }

    /**
     * Build BillAddr payload
     */
    private function buildBillAddress(?array $addr): ?array
    {
        if (empty($addr) || !is_array($addr)) {
            return null;
        }

        return array_filter([
            'Line1' => $addr['line1'] ?? null,
            'Line2' => $addr['line2'] ?? null,
            'Line3' => $addr['line3'] ?? null,
            'City'  => $addr['city'] ?? null,
            'Country' => $addr['country'] ?? null,
            'CountrySubDivisionCode' => $addr['state'] ?? null,
            'PostalCode' => $addr['postal_code'] ?? null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Retrieve all vendors
     */
    public function getAll(): object
    {
        $query = "SELECT * FROM Vendor";
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }
}
