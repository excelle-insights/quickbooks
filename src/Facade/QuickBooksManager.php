<?php

namespace ExcelleInsights\QuickBooks\Facade;

use PDO;
use ExcelleInsights\QuickBooks\Auth\Authentication;
use ExcelleInsights\QuickBooks\Client\CustomerClient;
use ExcelleInsights\QuickBooks\Repositories\TokenRepository;
use ExcelleInsights\QuickBooks\Support\EnvLoader;
use ExcelleInsights\QuickBooks\Repositories\QboCustomerRepository;
use ExcelleInsights\QuickBooks\Services\CustomerSyncService;

class QuickBooksManager
{
    private Authentication $auth;
    private PDO $pdo;
    private string $baseUrl;
    private string $companyId;

    public function __construct(?PDO $pdo = null, ?string $companyId = null, ?string $envRoot = null)
    {
        EnvLoader::load($envRoot);

        $this->baseUrl   = $_ENV['QBO_BASE_URL'] ?? '' ?: 'https://quickbooks.api.intuit.com/v3/company/';
        $this->companyId = $companyId ?? $_ENV['QBO_REALM_ID'] ?? null;

        if (!$pdo) {
            $dsn  = $_ENV['DB_DSN'] ?? null;
            $user = $_ENV['DB_USER'] ?? null;
            $pass = $_ENV['DB_PASSWORD'] ?? null;

            if (!$dsn) {
                throw new \RuntimeException(
                    'DB_DSN is not set. Ensure your project .env exists and is readable.'
                );
            }

            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        }

        $repo = new TokenRepository($pdo);

        $this->auth = new Authentication(
            $repo,
            'quickbooks',
            'quickbooks'
        );
    }

    public function getAuthUrl(): string
    {
        return $this->auth->getAuthUrl();
    }

    public function authenticate(string $code, string $realmId): void
    {
        $this->auth->exchangeAuthorizationCode($code, $realmId);
    }


    public function customers(): CustomerClient
    {
        return new CustomerClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth
        );
    }

    public function createCustomer(array $data): object
    {
        $repo = new QboCustomerRepository($this->pdo);

        $service = new CustomerSyncService(
            $repo,
            $this->customers()
        );

        return $service->create($data);
    }
}
