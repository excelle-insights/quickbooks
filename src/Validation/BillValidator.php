<?php

namespace ExcelleInsights\QuickBooks\Validation;

class BillValidator
{
    public function validateCreate(array $data): void
    {
        $required = [
            'qbo_company_id',
            'vendor_qbo_id',
            'items'
        ];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("{$field} is required.");
            }
        }

        if (!is_array($data['items']) || count($data['items']) === 0) {
            throw new \InvalidArgumentException('At least one bill item is required.');
        }

        foreach ($data['items'] as $index => $item) {

            if (empty($item['account_qbo_id'])) {
                throw new \InvalidArgumentException(
                    "account_qbo_id is required for item index {$index}."
                );
            }

            if (!isset($item['amount']) || !is_numeric($item['amount'])) {
                throw new \InvalidArgumentException(
                    "Valid amount is required for item index {$index}."
                );
            }

            if ((float)$item['amount'] <= 0) {
                throw new \InvalidArgumentException(
                    "Amount must be greater than zero for item index {$index}."
                );
            }
        }

        if (!empty($data['txn_date']) && !$this->isValidDate($data['txn_date'])) {
            throw new \InvalidArgumentException('txn_date must be in Y-m-d format.');
        }
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
