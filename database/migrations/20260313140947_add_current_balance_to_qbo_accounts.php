<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddCurrentBalanceToQboAccounts extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        if (!$this->hasTable('qbo_accounts')) {
            return;
        }
        
        $table = $this->table('qbo_accounts');
        if (!$table->hasColumn('current_balance')) {
            $table->addColumn('current_balance', 'decimal', [
                'precision' => 15,
                'scale' => 2,
                'default' => 0.00,
                'null' => false,
                'after' => 'classification'
            ])
                ->update();
        }
    }
}
