<?php
require '../../vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;
use ExcelleInsights\QuickBooks\Controller\OAuthController;

$qbo = new QuickBooksManager();
$oauth = new OAuthController($qbo);

// Display result of OAuth callback
echo $oauth->handleCallback();
