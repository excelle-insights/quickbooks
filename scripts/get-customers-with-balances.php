<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;
use ExcelleInsights\QuickBooks\Client\CustomerClient;

// Initialize the manager
$qbo = new QuickBooksManager();
echo json_encode($qbo->getCustomersWithBalances());


