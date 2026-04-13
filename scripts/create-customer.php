<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

// Initialize the manager
$qbo = new QuickBooksManager();

$index = 15;
$first_name = "Ruth";
$middle_name = "Blondee";
$last_name = "Langmore";

$result = $qbo->createCustomer([
    'qbo_company_id' => 1,
    'first_name'    => $first_name,
    'middle_name'   => $middle_name,
    'last_name'     => $last_name,
    'name'  => $first_name . ' ' . $middle_name . ' ' . $last_name,
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
