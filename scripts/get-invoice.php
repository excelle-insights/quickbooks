<?php
require '../../vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

$id = 169;
// Initialize the manager
$qbo = new QuickBooksManager();
echo json_encode($qbo->getInvoice($id));


