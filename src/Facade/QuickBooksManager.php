<?php

namespace ExcelleInsights\QuickBooks\Facade;

use PDO;
use ExcelleInsights\QuickBooks\Auth\Authentication;
use ExcelleInsights\QuickBooks\Client\CustomerClient;
use ExcelleInsights\QuickBooks\Client\InvoiceClient;
use ExcelleInsights\QuickBooks\Contracts\HttpClientInterface;
use ExcelleInsights\QuickBooks\Repositories\TokenRepository;
use ExcelleInsights\QuickBooks\Repositories\QboCustomerRepository;
use ExcelleInsights\QuickBooks\Repositories\QboInvoiceRepository;
use ExcelleInsights\QuickBooks\Services\CustomerSyncService;
use ExcelleInsights\QuickBooks\Services\InvoiceSyncService;
use ExcelleInsights\QuickBooks\Support\EnvLoader;

/**
 * Facade for QuickBooks integration
 * Keeps DX simple while wiring everything internally
 */
class QuickBooksManager
{
    private Authentication $auth;
    private PDO $pdo;
    private string $baseUrl;
    private string $companyId;
    private HttpClientInterface $http;

    public function __construct(
        ?HttpClientInterface $http = null,
        ?PDO $pdo = null,
        ?string $companyId = null,
        ?string $envRoot = null
    ) {
        EnvLoader::load($envRoot);

        $this->baseUrl   = $_ENV['QBO_BASE_URL']
            ?? 'https://quickbooks.api.intuit.com';
        $this->companyId = $companyId
            ?? $_ENV['QBO_REALM_ID']
            ?? '';

        if (!$pdo) {
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
        }

        $this->pdo = $pdo;

                /**
         * 🔌 HTTP client
         * Default is instantiated internally
         */
        if ($http === null) {
            // ⚠️ DO NOT hard-reference CRM classes in the package
            // Replace this with a factory later if needed
            $http = new \ExcelleInsights\QuickBooks\Support\DefaultHttpClient($this->pdo);
        }

        $this->http = $http;

        $tokenRepo = new TokenRepository($pdo);
        $this->auth = new Authentication(
            $tokenRepo,
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

    /**
     * -------------------------
     * Customers
     * -------------------------
     */
    public function createCustomer(array $data): object
    {
        $repo = new QboCustomerRepository($this->pdo);

        $client = new CustomerClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $service = new CustomerSyncService($repo, $client);

        return $service->create($data);
    }

    /**
     * -------------------------
     * Invoices
     * -------------------------
     */
    public function createInvoice(array $data): object
    {
        if (empty($data['qbo_company_id'])) {
            throw new \InvalidArgumentException('qbo_company_id is required');
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new \InvalidArgumentException('Invoice items are required');
        }

        $invoiceRepo  = new QboInvoiceRepository($this->pdo);
        $customerRepo = new QboCustomerRepository($this->pdo);

        $client = new InvoiceClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $service = new InvoiceSyncService(
            $invoiceRepo,
            $customerRepo,
            $client
        );

        return $service->create($data);
    }
}
