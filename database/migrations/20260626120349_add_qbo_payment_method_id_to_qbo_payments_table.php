<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddQboPaymentMethodIdToQboPaymentsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table(($_ENV['QBO_TABLE_PREFIX'] ?? 'qbo') . '_payments');

        if (!$table->hasColumn('qbo_payment_method_id')) {
            $table->addColumn('qbo_payment_method_id', 'string', [
                'null' => true,
                'after' => 'payment_ref',
                'comment' => 'QuickBooks Payment Method ID'
            ])->update();
        }
    }
}
