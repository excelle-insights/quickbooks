<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

$qbo = new QuickBooksManager();

$result = $qbo->syncTaxCodes(qboCompanyId: 1);

echo "Synced {$result['synced']} tax code(s) from QuickBooks:" . PHP_EOL;

foreach ($result['items'] as $tc) {
    echo "  [{$tc['local_id']}] {$tc['name']} (QBO ID: {$tc['qbo_id']})" . PHP_EOL;
}
