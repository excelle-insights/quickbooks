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

    public function __construct(?PDO $pdo = null, ?string $companyId = null)
    {
        // Load package env automatically
        EnvLoader::load();

        $this->baseUrl   = getenv('QBO_BASE_URL');
        $this->companyId = $companyId ?? getenv('QBO_REALM_ID');

        $pdo ??= new PDO(
            getenv('DB_DSN'),
            getenv('DB_USER'),
            getenv('DB_PASSWORD')
        );

        $repo = new TokenRepository($pdo);

        $this->auth = new Authentication(
            $repo,
            'quickbooks',
            'quickbooks'
        );
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
