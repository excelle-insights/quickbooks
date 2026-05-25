<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Expands the description column in qbo_bill_items from varchar(255) to text.
 * Invoice line descriptions can exceed 255 characters, causing:
 *   SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'description'
 */
final class ExpandDescriptionInQboBillItems extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
            ALTER TABLE qbo_bill_items
            MODIFY COLUMN description TEXT NULL
        ");
    }

    public function down(): void
    {
        // Truncate any existing data that exceeds 255 chars before reverting
        $this->execute("
            UPDATE qbo_bill_items
            SET description = LEFT(description, 255)
            WHERE LENGTH(description) > 255
        ");
        $this->execute("
            ALTER TABLE qbo_bill_items
            MODIFY COLUMN description VARCHAR(255) NULL
        ");
    }
}
