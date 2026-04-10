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
     * Pull all active tax codes from QBO, upsert into qbo_tax_codes,
     * and automatically merge into the local tax_types table.
     *
     * Match priority: qbo_id already linked → name (case-insensitive) → rate
     * If no match found, insert a new tax_types row.
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
            // 1. Upsert into qbo_tax_codes
            $localId = $this->taxCodes->upsert([
                'qbo_company_id' => $qboCompanyId,
                'qbo_id'         => $tc->Id,
                'name'           => $tc->Name,
                'description'    => $tc->Description ?? null,
                'taxable'        => ($tc->Taxable ?? false) ? 1 : 0,
                'active'         => ($tc->Active ?? true) ? 1 : 0,
                'sync_token'     => $tc->SyncToken ?? null,
            ]);

            // 2. Merge into local tax_types and stamp local_tax_id back
            $localTaxId = $this->mergeIntoTaxTypes($localId, $tc);

            $synced[] = [
                'local_id'     => $localId,
                'qbo_id'       => $tc->Id,
                'name'         => $tc->Name,
                'local_tax_id' => $localTaxId,
            ];
        }

        return [
            'synced' => count($synced),
            'items'  => $synced,
        ];
    }

    /**
     * Find or create a matching tax_types row and link it to the qbo_tax_codes row.
     *
     * Match priority:
     *   1. qbo_tax_codes.local_tax_id already set (already linked — just return it)
     *   2. tax_types.tax_name matches QBO name (case-insensitive)
     *   3. Insert new tax_types row from QBO data
     *
     * @return int  The tax_types.id that was matched or created
     */
    private function mergeIntoTaxTypes(int $qboTaxCodeLocalId, object $tc): int
    {
        $pdo  = $this->taxCodes->getPdo();
        $name = trim($tc->Name);

        // Ensure local_tax_id column exists (added by settings_tax_types.php inline ALTER,
        // but we guard here too so the service never breaks on a fresh install)
        $this->ensureLocalTaxIdColumn($pdo);

        // Priority 1: already linked
        $stmt = $pdo->prepare("SELECT local_tax_id FROM qbo_tax_codes WHERE id = ?");
        $stmt->execute([$qboTaxCodeLocalId]);
        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        if ($row && !empty($row->local_tax_id)) {
            return (int) $row->local_tax_id;
        }

        // Priority 2: match by name in tax_types
        $stmt = $pdo->prepare("
            SELECT id FROM tax_types
            WHERE LOWER(TRIM(tax_name)) = LOWER(?)
            LIMIT 1
        ");
        $stmt->execute([$name]);
        $existing = $stmt->fetch(\PDO::FETCH_OBJ);

        if ($existing) {
            $localTaxId = (int) $existing->id;
        } else {
            // Priority 3: insert new tax_types row
            // Determine tax_type: non-taxable codes map to 'normal' (exempt/zero-rated),
            // taxable ones are also 'normal' — withholding is a CRM-only concept.
            $taxType = 'normal';

            $stmt = $pdo->prepare("
                INSERT INTO tax_types (tax_name, tax_type, tax_rate, description, status, created_by, created_at)
                VALUES (?, ?, ?, ?, 'active', 1, NOW())
            ");
            $stmt->execute([
                $name,
                $taxType,
                0.00, // QBO TaxCode doesn't carry a flat rate; rate lives on TaxRate sub-entity
                $tc->Description ?? 'Imported from QuickBooks',
            ]);
            $localTaxId = (int) $pdo->lastInsertId();
        }

        // Stamp local_tax_id back onto qbo_tax_codes row
        $stmt = $pdo->prepare("UPDATE qbo_tax_codes SET local_tax_id = ? WHERE id = ?");
        $stmt->execute([$localTaxId, $qboTaxCodeLocalId]);

        return $localTaxId;
    }

    /**
     * Guard: add local_tax_id column to qbo_tax_codes if it doesn't exist yet.
     */
    private function ensureLocalTaxIdColumn(\PDO $pdo): void
    {
        $stmt = $pdo->query("SHOW COLUMNS FROM qbo_tax_codes LIKE 'local_tax_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE qbo_tax_codes ADD COLUMN local_tax_id INT NULL DEFAULT NULL AFTER qbo_id");
        }
    }
}
