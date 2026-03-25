<?php

namespace ExcelleInsights\QuickBooks\Services;

use ExcelleInsights\QuickBooks\Client\TaxCodeClient;
use ExcelleInsights\QuickBooks\Repositories\QboTaxCodeRepository;

class TaxCodeSyncService
{
    public function __construct(
        private QboTaxCodeRepository $taxCodes,
        private TaxCodeClient $qbo
    ) {}

    /**
     * Pull all active tax codes from QBO and upsert locally.
     * Call this once during setup, or periodically to stay in sync.
     *
     * @param int $qboCompanyId  Local company ID
     * @return array             Summary of synced tax codes
     */
    public function sync(int $qboCompanyId): array
    {
        $response = $this->qbo->getAll();

        $taxCodeList = $response->QueryResponse->TaxCode ?? [];

        if (empty($taxCodeList)) {
            return ['synced' => 0, 'items' => []];
        }

        $synced = [];

        foreach ($taxCodeList as $tc) {
            $localId = $this->taxCodes->upsert([
                'qbo_company_id' => $qboCompanyId,
                'qbo_id'         => $tc->Id,
                'name'           => $tc->Name,
                'description'    => $tc->Description ?? null,
                'taxable'        => ($tc->Taxable ?? false) ? 1 : 0,
                'active'         => ($tc->Active ?? true) ? 1 : 0,
                'sync_token'     => $tc->SyncToken ?? null,
            ]);

            $synced[] = [
                'local_id' => $localId,
                'qbo_id'   => $tc->Id,
                'name'     => $tc->Name,
            ];
        }

        return [
            'synced' => count($synced),
            'items'  => $synced,
        ];
    }
}
