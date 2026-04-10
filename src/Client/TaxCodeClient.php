<?php

namespace ExcelleInsights\QuickBooks\Client;

class TaxCodeClient extends BaseClient
{
    /**
     * Fetch all tax codes from QuickBooks Online.
     * QBO is the source of truth — we pull, never push.
     */
    public function getAll(): object
    {
        $query = 'SELECT * FROM TaxCode WHERE Active = true MAXRESULTS 200';
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }

    /**
     * Fetch all TaxRate entities — these carry the actual RateValue (%).
     * TaxCode references TaxRate via SalesTaxRateList.TaxRateDetail[].TaxRateRef.value
     */
    public function getAllTaxRates(): object
    {
        $query = 'SELECT * FROM TaxRate MAXRESULTS 200';
        return $this->sendRequest('GET', $this->endpoint('query?query=' . rawurlencode($query)));
    }
}
