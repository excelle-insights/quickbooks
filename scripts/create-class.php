<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

// Initialize the manager
$qbo = new QuickBooksManager();

$index = 1; // Example index for testing

try {
    $result = $qbo->createClass([
        'qbo_company_id' => 1,          // Required
        'name'           => "Parking Complex",

        // Optional fields
        'parent_id'      => 1,       // Can be set to an existing local class id
        'active'         => true,       // Default is true
    ]);

    if ($result->status === 'synced') {
        echo "Class synced with QBO ID: " . $result->qbo_id . PHP_EOL;
    } else {
        echo "Class queued for retry. Local ID: " . $result->local_id . PHP_EOL;
        if (isset($result->error)) {
            echo "Error: " . $result->error . PHP_EOL;
        }
    }
} catch (\Exception $e) {
    echo "Failed to create class: " . $e->getMessage() . PHP_EOL;
}
