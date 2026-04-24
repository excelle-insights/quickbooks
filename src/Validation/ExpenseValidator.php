<?php

namespace ExcelleInsights\QuickBooks\Validation;

class ExpenseValidator
{
    public static function validate(array $data): void
    {
        (new self())->validateCreate($data);
    }

    public function validateCreate(array $data): void
    {
        foreach (['qbo_company_id', 'payment_account_qbo_id', 'items'] as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("{$field} is required.");
            }
        }

        if (!is_array($data['items']) || count($data['items']) === 0) {
            throw new \InvalidArgumentException('At least one expense item (category line) is required.');
        }

        foreach ($data['items'] as $index => $item) {
            if (empty($item['account_qbo_id'])) {
                throw new \InvalidArgumentException("account_qbo_id is required for item index {$index}.");
            }

            if (!isset($item['amount']) || !is_numeric($item['amount'])) {
                throw new \InvalidArgumentException("Valid amount is required for item index {$index}.");
            }

            if ((float) $item['amount'] <= 0) {
                throw new \InvalidArgumentException("Amount must be greater than zero for item index {$index}.");
            }
        }

        $validPaymentTypes = ['Cash', 'Check', 'CreditCard'];
        if (!empty($data['payment_type']) && !in_array($data['payment_type'], $validPaymentTypes)) {
            throw new \InvalidArgumentException(
                'payment_type must be one of: ' . implode(', ', $validPaymentTypes)
            );
        }

        if (!empty($data['txn_date'])) {
            $d = \DateTime::createFromFormat('Y-m-d', $data['txn_date']);
            if (!$d || $d->format('Y-m-d') !== $data['txn_date']) {
                throw new \InvalidArgumentException('txn_date must be in Y-m-d format.');
            }
        }
    }
}
