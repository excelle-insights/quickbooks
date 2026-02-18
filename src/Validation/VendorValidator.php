<?php

namespace ExcelleInsights\QuickBooks\Validation;

use InvalidArgumentException;

class VendorValidator
{
    public static function validate(array $data): void
    {
        self::requireField($data, 'qbo_company_id');
        self::requireField($data, 'display_name');

        self::validateEmail($data);
        self::validatePhone($data);
        self::validateBillingAddress($data);
        self::validateBooleanFields($data);
    }

    private static function requireField(array $data, string $field): void
    {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            throw new InvalidArgumentException("{$field} is required");
        }
    }

    private static function validateEmail(array $data): void
    {
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address');
        }
    }

    private static function validatePhone(array $data): void
    {
        foreach (['phone', 'mobile'] as $field) {
            if (!empty($data[$field]) && !is_string($data[$field])) {
                throw new InvalidArgumentException("{$field} must be a string");
            }
        }
    }

    private static function validateBillingAddress(array $data): void
    {
        if (!isset($data['bill_addr'])) {
            return;
        }

        if (!is_array($data['bill_addr'])) {
            throw new InvalidArgumentException('bill_addr must be an array');
        }

        $allowed = [
            'line1',
            'line2',
            'line3',
            'city',
            'postal_code',
            'country',
            'country_sub_division_code',
        ];

        foreach ($data['bill_addr'] as $key => $_) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("Invalid bill_addr field: {$key}");
            }
        }
    }

    private static function validateBooleanFields(array $data): void
    {
        foreach (['active', 'sub_vendor'] as $field) {
            if (isset($data[$field]) && !is_bool($data[$field])) {
                throw new InvalidArgumentException("{$field} must be boolean");
            }
        }
    }
}
