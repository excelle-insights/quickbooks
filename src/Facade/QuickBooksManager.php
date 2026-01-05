<?php

namespace ExcelleInsights\QuickBooks\Facade;

use PDO;
use ExcelleInsights\QuickBooks\Auth\Authentication;
use ExcelleInsights\QuickBooks\Client\CustomerClient;
use ExcelleInsights\QuickBooks\Repositories\TokenRepository;
use ExcelleInsights\QuickBooks\Support\EnvLoader;

class QuickBooksManager
{
    private Authentication $auth;
    private string $baseUrl;
    private string $companyId;

    public function __construct(?PDO $pdo = null, ?string $companyId = null, ?string $envRoot = null)
    {
        EnvLoader::load($envRoot);

        $this->baseUrl   = getenv('QBO_BASE_URL') ?: '';
        $this->companyId = $companyId ?? getenv('QBO_REALM_ID');

        if (!$pdo) {
            $dsn  = getenv('DB_DSN');
            $user = getenv('DB_USER');
            $pass = getenv('DB_PASSWORD');

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
}
