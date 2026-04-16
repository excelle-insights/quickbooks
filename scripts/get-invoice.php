<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

$id = 250;
// Initialize the manager
$qbo = new QuickBooksManager();
echo json_encode($qbo->getInvoice($id));


