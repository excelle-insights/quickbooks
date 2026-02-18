<?php
require dirname(__DIR__) . '/vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;
use ExcelleInsights\QuickBooks\Support\EnvLoader;
use ExcelleInsights\QuickBooks\Repositories\QboAccountRepository;
use PDO;

$envRoot = dirname(__DIR__);
// EnvLoader::load($envRoot);

// Initialize the manager
$qbo = new QuickBooksManager();
$response = $qbo->getAllAccounts();

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

$accountRepo = new QboAccountRepository($pdo);

// Check if the response is successful by checking if it has property 'Account'
if (property_exists($response->QueryResponse, 'Account')) {
    foreach ($response->QueryResponse->Account as $account) {
        echo "Account Name: " . $account->Name . ", Account ID: " . $account->Id . "\n";

        $data = [
            'qbo_company_id'   => 1,
            'name'             => $account->Name,
            'account_type'     => $account->AccountType,
            'account_sub_type' => $account->AccountSubType,
            'classification'   => $account->Classification,
            'description'      => 'Test income account created from SDK',
            'active'           => true,
        ];

        $local_id = $accountRepo->create($data);

        if($local_id) {
            $accountRepo->markSynced($local_id, $account->Id, $account->SyncToken);
        }
    }
} else {
    echo "Failed to fetch accounts. Response: " . json_encode($response);
}
