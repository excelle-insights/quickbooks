<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;
use ExcelleInsights\QuickBooks\Controller\OAuthController;

// Initialize
$qbo = new QuickBooksManager(null, null, dirname(__DIR__, 2));
$oauth = new OAuthController($qbo);

echo $qbo->getAuthUrl();