<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

// Initialize the manager
$qbo = new QuickBooksManager();

$index = 13;

$result = $qbo->createVendor([
    'qbo_company_id' => 1,

    // Required
    'display_name'  => "Test Vendor {$index}",

    // Optional fields
    'given_name'    => 'Dianne',
    'family_name'   => 'Bradley',
    'company_name'  => "Test Vendor Company {$index}",
    'email'         => "vendor{$index}@example.com",
    'phone'         => "+25472456565{$index}",
    'mobile'        => "+2547000000{$index}",
    'tax_identifier'=> "99-56882{$index}",
    'acct_num'      => "ACC-{$index}",

    // Billing address
    'bill_addr' => [
        'line1'                     => "Vendor Line 1 {$index}",
        'line2'                     => "Vendor Line 2 {$index}",
        'city'                      => 'Nairobi',
        'postal_code'               => '00100',
        'country'                   => 'Kenya',
        'country_sub_division_code' => 'KE',
    ],

    // Meta
    'active' => true,
]);

if ($result->status === 'synced') {
    echo "Vendor synced with QBO ID: " . $result->qbo_id . PHP_EOL;
} else {
    echo "Vendor queued for retry. Local ID: " . $result->local_id . PHP_EOL;
}
