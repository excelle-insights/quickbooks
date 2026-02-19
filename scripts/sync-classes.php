<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;
use ExcelleInsights\QuickBooks\Support\EnvLoader;
use ExcelleInsights\QuickBooks\Repositories\QboClassRepository;
use PDO;

$envRoot = dirname(__DIR__);
// EnvLoader::load($envRoot);

// Initialize the manager
$qbo = new QuickBooksManager();
$response = $qbo->getAllClasss();

$dsn  = $_ENV['DB_DSN'] ?? null;
$user = $_ENV['DB_USER'] ?? null;
$pass = $_ENV['DB_PASSWORD'] ?? null;

if (!$dsn) {
    throw new \RuntimeException(
        'DB_DSN is not set. Ensure your project .env exists.'
    );
}

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$classRepo = new QboClassRepository($pdo);

// Check if the response is successful by checking if it has property 'Class'
if (property_exists($response->QueryResponse, 'Class')) {
    foreach ($response->QueryResponse->Class as $class) {
        echo "Class Name: " . $class->Name . ", Class ID: " . $class->Id . "\n";

        $data = [
            'qbo_company_id'   => 1,
            'name'             => $class->Name,
            'active'           => true,
        ];

        $local_id = $classRepo->create($data);

        if($local_id) {
            $classRepo->markSynced($local_id, $class->Id, $class->SyncToken);
        }
    }
} else {
    echo "Failed to fetch classs. Response: " . json_encode($response);
}
