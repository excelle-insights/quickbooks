<?php
require '../../vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

// Initialize the manager
$qbo = new QuickBooksManager();

$index = 13;

$result = $qbo->createCustomer([
    'qbo_company_id' => 1,
    'name'  => 'Test Customer'.$index,
    'email'         => 'testcustomer'.$index.'@email.com',
    'phone'         => '+254724565654'.$index,
    'company_name' => 'Test Company '.$index,
    'country'      => 'Kenya',
    'city'         => 'Nairobi',
    'postal_code'  => '00100',
    'line'         => 'Ngong Road',
]);

if ($result->status === 'synced') {
    echo "Customer synced with QBO ID: " . $result->qbo_id;
} else {
    echo "Customer queued for retry. Local ID: " . $result->local_id;
}
