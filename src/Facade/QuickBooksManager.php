<?php

namespace ExcelleInsights\QuickBooks\Facade;

use PDO;
use ExcelleInsights\QuickBooks\Auth\Authentication;
use ExcelleInsights\QuickBooks\Client\CustomerClient;
use ExcelleInsights\QuickBooks\Repositories\TokenRepository;
use ExcelleInsights\QuickBooks\Support\EnvLoader;
use ExcelleInsights\QuickBooks\Repositories\QboCustomerRepository;
use ExcelleInsights\QuickBooks\Services\CustomerSyncService;
use ExcelleInsights\QuickBooks\Services\InvoiceSyncService;
use ExcelleInsights\QuickBooks\Repositories\QboInvoiceRepository;
use ExcelleInsights\QuickBooks\Repositories\QboInvoiceItemRepository;
use ExcelleInsights\QuickBooks\Client\InvoiceClient;
use ExcelleInsights\QuickBooks\Contracts\HttpClientInterface;

class QuickBooksManager
{
    private Authentication $auth;
    private PDO $pdo;
    private string $baseUrl;
    private string $companyId;
    private HttpClientInterface $http;

    public function __construct(HttpClientInterface $http, ?PDO $pdo = null, ?string $companyId = null, ?string $envRoot = null)
    {
        EnvLoader::load($envRoot);

        $this->http = $http;

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

        $this->pdo = $pdo;
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



    public function createCustomer(array $data): object
    {
        $repo = new QboCustomerRepository($this->pdo);

        $client = new CustomerClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $service = new CustomerSyncService(
            $repo,
            $client
        );

        return $service->create($data);
    }

    public function createInvoice(array $data): object
    {
        if (empty($data['qbo_company_id'])) {
            throw new \InvalidArgumentException('qbo_company_id is required');
        }

        if (empty($data['qbo_customer_id'])) {
            throw new \InvalidArgumentException('qbo_customer_id is required');
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new \InvalidArgumentException('Invoice items are required');
        }

        // Local repositories
        $invoiceRepo = new QboInvoiceRepository($this->pdo);
        $customerRepo = new QboCustomerRepository($this->pdo);

        // QBO client
        $client = new InvoiceClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth
        );

        // Sync service handles local + QBO creation
        $service = new InvoiceSyncService(
            $invoiceRepo,
            $customerRepo, 
            $client
        );

        // Create and return the invoice
        return $service->create($data);
    }


    public function createPayment(array $data): object
    {
        $repo = new QboCustomerRepository($this->pdo);

        $service = new CustomerSyncService(
            $repo,
            $this->customers()
        );

        return $service->create($data);
    }
}
