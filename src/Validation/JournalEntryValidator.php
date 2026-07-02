<?php

namespace ExcelleInsights\QuickBooks\Validation;

class JournalEntryValidator
{
    public static function validate(array $data): void
    {
        if (empty($data['local_id'])) {
            throw new \InvalidArgumentException('local_id is required');
        }

        if (empty($data['txn_date'])) {
            throw new \InvalidArgumentException('txn_date is required');
        }

        if (empty($data['lines']) || !is_array($data['lines'])) {
            throw new \InvalidArgumentException('Journal entry lines are required');
        }

        self::assertBalanced($data['lines']);
    }

    private static function assertBalanced(array $lines): void
    {
        $debits  = 0;
        $credits = 0;

        foreach ($lines as $line) {
            $debits  += (float) ($line['debit'] ?? 0);
            $credits += (float) ($line['credit'] ?? 0);
        }

        if (round($debits, 2) !== round($credits, 2)) {
            throw new \InvalidArgumentException(
                'Journal Entry is not balanced. Debits must equal Credits.'
            );
        }
    }
}
