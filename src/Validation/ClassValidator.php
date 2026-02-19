<?php

namespace ExcelleInsights\QuickBooks\Validation;

use InvalidArgumentException;

class ClassValidator
{
    /**
     * Validate data before creating a QBO Class
     *
     * @param array $data
     * @throws InvalidArgumentException
     */
    public static function validate(array $data): void
    {
        // qbo_company_id is required
        if (empty($data['qbo_company_id'])) {
            throw new InvalidArgumentException('qbo_company_id is required.');
        }

        // name is required
        if (empty($data['name']) || !is_string($data['name'])) {
            throw new InvalidArgumentException('Class name is required and must be a string.');
        }

        // parent_id is optional but if present must be an integer
        if (isset($data['parent_id']) && !is_numeric($data['parent_id'])) {
            throw new InvalidArgumentException('parent_id must be a valid integer referencing another class.');
        }

        // active is optional but if present must be boolean
        if (isset($data['active']) && !is_bool($data['active'])) {
            throw new InvalidArgumentException('active must be a boolean value.');
        }

        // Optional: Trim string fields
        $data['name'] = trim($data['name']);
    }
}
