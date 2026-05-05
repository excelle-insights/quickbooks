<?php

namespace ExcelleInsights\QuickBooks\Facade;

use PDO;
use ExcelleInsights\QuickBooks\Support\EnvLoader;
use ExcelleInsights\QuickBooks\Auth\Authentication;

use ExcelleInsights\QuickBooks\Client\CustomerClient;
use ExcelleInsights\QuickBooks\Client\InvoiceClient;
use ExcelleInsights\QuickBooks\Client\PaymentClient;
use ExcelleInsights\QuickBooks\Client\AccountClient;
use ExcelleInsights\QuickBooks\Client\JournalEntryClient;
use ExcelleInsights\QuickBooks\Client\VendorClient;
use ExcelleInsights\QuickBooks\Client\ClassClient;
use ExcelleInsights\QuickBooks\Client\BillClient;
use ExcelleInsights\QuickBooks\Client\BillPaymentClient;
use ExcelleInsights\QuickBooks\Client\TaxCodeClient;
use ExcelleInsights\QuickBooks\Client\ExpenseClient;
use ExcelleInsights\QuickBooks\Client\ItemClient;

use ExcelleInsights\QuickBooks\Contracts\HttpClientInterface;
use ExcelleInsights\QuickBooks\Repositories\TokenRepository;
use ExcelleInsights\QuickBooks\Repositories\QboCustomerRepository;
use ExcelleInsights\QuickBooks\Repositories\QboInvoiceRepository;
use ExcelleInsights\QuickBooks\Repositories\QboInvoiceItemRepository;
use ExcelleInsights\QuickBooks\Repositories\QboPaymentRepository;
use ExcelleInsights\QuickBooks\Repositories\QboPaymentItemRepository;
use ExcelleInsights\QuickBooks\Repositories\QboAccountRepository;
use ExcelleInsights\QuickBooks\Repositories\QboJournalEntryRepository;
use ExcelleInsights\QuickBooks\Repositories\QboJournalEntryLineRepository;
use ExcelleInsights\QuickBooks\Repositories\QboVendorRepository;
use ExcelleInsights\QuickBooks\Repositories\QboClassRepository;
use ExcelleInsights\QuickBooks\Repositories\QboBillRepository;
use ExcelleInsights\QuickBooks\Repositories\QboBillItemRepository;
use ExcelleInsights\QuickBooks\Repositories\QboTaxCodeRepository;
use ExcelleInsights\QuickBooks\Repositories\QboExpenseRepository;
use ExcelleInsights\QuickBooks\Repositories\QboExpenseItemRepository;
use ExcelleInsights\QuickBooks\Repositories\QboItemRepository;

use ExcelleInsights\QuickBooks\Services\CustomerSyncService;
use ExcelleInsights\QuickBooks\Services\InvoiceSyncService;
use ExcelleInsights\QuickBooks\Services\PaymentSyncService;
use ExcelleInsights\QuickBooks\Services\AccountSyncService;
use ExcelleInsights\QuickBooks\Services\JournalEntrySyncService;
use ExcelleInsights\QuickBooks\Services\VendorSyncService;
use ExcelleInsights\QuickBooks\Services\ClassSyncService;
use ExcelleInsights\QuickBooks\Services\BillSyncService;
use ExcelleInsights\QuickBooks\Services\TaxCodeSyncService;
use ExcelleInsights\QuickBooks\Services\ExpenseSyncService;
use ExcelleInsights\QuickBooks\Services\ItemSyncService;

use ExcelleInsights\QuickBooks\Validation\JournalEntryValidator;
use ExcelleInsights\QuickBooks\Validation\VendorValidator;
use ExcelleInsights\QuickBooks\Validation\ClassValidator;
use ExcelleInsights\QuickBooks\Validation\BillValidator;
use ExcelleInsights\QuickBooks\Validation\ExpenseValidator;

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

        if ($http === null) {
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

    public function getPdo(): PDO
    {
        return $this->pdo;
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

    public function getCustomer($id)
    {
        $client = new CustomerClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );
        return $client->getById($id);
    }

    public function getAllCustomers(int $startPosition = 1, int $maxResults = 1000)
    {
        $client = new CustomerClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );
        return $client->getAll($startPosition, $maxResults);
    }
    public function getCustomersWithBalances(int $startPosition = 1, int $maxResults = 1000)
    {
        $client = new CustomerClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );
        return $client->getWithOutstandingBalances($startPosition, $maxResults);
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

        $invoiceRepo     = new QboInvoiceRepository($this->pdo);
        $invoiceItemRepo = new QboInvoiceItemRepository($this->pdo);
        $customerRepo    = new QboCustomerRepository($this->pdo);
        $classRepo       = new QboClassRepository($this->pdo);
        $itemRepo        = new QboItemRepository($this->pdo);

        $client = new InvoiceClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $service = new InvoiceSyncService(
            $invoiceRepo,
            $invoiceItemRepo,
            $customerRepo,
            $classRepo,
            $itemRepo,
            $client,
            $this->pdo
        );

        return $service->create($data);
    }

    public function getInvoice($id)
    {
        $client = new InvoiceClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );
        return $client->getById($id);
    }

    /**
     * -------------------------
     * Payments
     * -------------------------
     */
    public function createPayment(array $data): object
    {
        if (empty($data['qbo_company_id'])) {
            throw new \InvalidArgumentException('qbo_company_id is required');
        }

        if (empty($data['qbo_customer_id'])) {
            throw new \InvalidArgumentException('qbo_customer_id is required');
        }

        $paymentRepo     = new QboPaymentRepository($this->pdo);
        $paymentItemRepo = new QboPaymentItemRepository($this->pdo);
        $customerRepo    = new QboCustomerRepository($this->pdo);
        $invoiceRepo     = new QboInvoiceRepository($this->pdo);

        $client = new PaymentClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $service = new PaymentSyncService(
            $paymentRepo,
            $paymentItemRepo,
            $customerRepo,
            $invoiceRepo,
            $client
        );

        return $service->create($data);
    }

    /**
     * -------------------------
     * Accounts
     * -------------------------
     */
    public function createAccount(array $data): object
    {
        if (empty($data['qbo_company_id'])) {
            throw new \InvalidArgumentException('qbo_company_id is required');
        }

        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Account name is required');
        }

        if (empty($data['account_type'])) {
            throw new \InvalidArgumentException('account_type is required');
        }

        $accountRepo = new QboAccountRepository($this->pdo);

        $client = new AccountClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $service = new AccountSyncService(
            $accountRepo,
            $client
        );

        return $service->create($data);
    }

    public function getAllAccounts()
    {
        $client = new AccountClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );
        return $client->getAll();
    }

    /**
     * -------------------------
     * Journal Entries
     * -------------------------
     */
    public function createJournalEntry(array $data): object
    {
        JournalEntryValidator::validate($data);

        $data['txn_date']    = date('Y-m-d', strtotime($data['txn_date']));
        $data['doc_number'] ??= null;

        $jeRepo     = new QboJournalEntryRepository($this->pdo);
        $jeItemRepo = new QboJournalEntryLineRepository($this->pdo);

        $client = new JournalEntryClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $service = new JournalEntrySyncService(
            $jeRepo,
            $jeItemRepo,
            $client
        );

        return $service->create($data);
    }

    /**
     * -------------------------
     * Vendors
     * -------------------------
     */
    public function createVendor(array $data): object
    {
        VendorValidator::validate($data);

        $vendorRepo = new QboVendorRepository($this->pdo);

        $client = new VendorClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $service = new VendorSyncService(
            $vendorRepo,
            $client
        );

        return $service->create($data);
    }

    public function getAllVendors(): object
    {
        $client = new VendorClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        return $client->getAll();
    }

    /**
     * -------------------------
     * Classes
     * -------------------------
     */
    public function createClass(array $data): object
    {
        ClassValidator::validate($data);

        $data['active'] ??= true;

        $classRepo = new QboClassRepository($this->pdo);

        $client = new ClassClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $service = new ClassSyncService(
            $classRepo,
            $client
        );

        return $service->create($data);
    }

    public function getAllClasses()
    {
        $client = new ClassClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );
        return $client->getAll();
    }

    /**
     * -------------------------
     * Bills
     * -------------------------
     */
    public function createBill(array $data): object
    {
        BillValidator::validate($data);

        if (empty($data['qbo_company_id'])) {
            throw new \InvalidArgumentException('qbo_company_id is required');
        }

        if (empty($data['items']) || !is_array($data['items'])) {
            throw new \InvalidArgumentException('Bill items are required');
        }

        $billRepo     = new QboBillRepository($this->pdo);
        $billItemRepo = new QboBillItemRepository($this->pdo);
        $vendorRepo   = new QboVendorRepository($this->pdo);
        $classRepo    = new QboClassRepository($this->pdo);
        $taxCodeRepo  = new QboTaxCodeRepository($this->pdo);

        $client = new BillClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $service = new BillSyncService(
            $billRepo,
            $billItemRepo,
            $vendorRepo,
            $classRepo,
            $taxCodeRepo,
            $client
        );

        return $service->create($data);
    }

    public function getAllBills(int $maxResults = 1000, int $startPosition = 1): object
    {
        $client = new BillClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        return $client->getAll($maxResults, $startPosition);
    }

    /**
     * -------------------------
     * Bill Payments
     * -------------------------
     */
    public function createBillPayment(array $data): object
    {
        if (empty($data['qbo_company_id'])) {
            throw new \InvalidArgumentException('qbo_company_id is required');
        }

        if (empty($data['vendor_qbo_id'])) {
            throw new \InvalidArgumentException('vendor_qbo_id is required');
        }

        if (empty($data['bill_payments']) || !is_array($data['bill_payments'])) {
            throw new \InvalidArgumentException('Bill payments are required');
        }

        $client = new BillPaymentClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $response = $client->create($data);

        if (isset($response->BillPayment)) {
            return (object)[
                'status' => 'synced',
                'qbo_id' => $response->BillPayment->Id,
                'data'   => $response->BillPayment
            ];
        }

        return (object)[
            'status' => 'failed',
            'error'  => 'Invalid response from QuickBooks'
        ];
    }

    /**
     * -------------------------
     * Tax Codes
     * -------------------------
     */
    public function syncTaxCodes(int $qboCompanyId): array
    {
        $taxCodeRepo = new QboTaxCodeRepository($this->pdo);

        $client = new TaxCodeClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $service = new TaxCodeSyncService($taxCodeRepo, $client);

        return $service->sync($qboCompanyId);
    }

    /**
     * -------------------------
     * Expenses (direct procurement)
     * -------------------------
     */
    public function createExpense(array $data): object
    {
        ExpenseValidator::validate($data);

        $expenseRepo     = new QboExpenseRepository($this->pdo);
        $expenseItemRepo = new QboExpenseItemRepository($this->pdo);
        $vendorRepo      = new QboVendorRepository($this->pdo);
        $classRepo       = new QboClassRepository($this->pdo);
        $taxCodeRepo     = new QboTaxCodeRepository($this->pdo);

        $client = new ExpenseClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $service = new ExpenseSyncService(
            $expenseRepo,
            $expenseItemRepo,
            $vendorRepo,
            $classRepo,
            $taxCodeRepo,
            $client
        );

        return $service->create($data);
    }

    public function getAllExpenses(int $maxResults = 1000, int $startPosition = 1): object
    {
        $client = new ExpenseClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        return $client->getAll($maxResults, $startPosition);
    }

    /**
     * -------------------------
     * Bank Accounts
     * -------------------------
     */
    public function getAllBankAccounts(): object
    {
        $client = new AccountClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $allAccounts  = $client->getAll();
        $bankAccounts = [];

        if (isset($allAccounts->QueryResponse->Account)) {
            foreach ($allAccounts->QueryResponse->Account as $account) {
                if (
                    in_array($account->AccountType, ['Bank', 'Other Current Asset']) &&
                    in_array($account->AccountSubType, ['Checking', 'Savings', 'MoneyMarket', 'CashOnHand'])
                ) {
                    $bankAccounts[] = $account;
                }
            }
        }

        return (object)[
            'QueryResponse' => (object)[
                'Account' => $bankAccounts
            ]
        ];
    }

    public function syncBankAccountsToLocal(): array
    {
        try {
            $qboBankAccounts = $this->getAllBankAccounts();

            $results       = [];
            $success_count = 0;
            $error_count   = 0;

            if (isset($qboBankAccounts->QueryResponse->Account)) {
                foreach ($qboBankAccounts->QueryResponse->Account as $account) {
                    try {
                        $stmt = $this->pdo->prepare("
                            SELECT id FROM qbo_bank 
                            WHERE qbo_company_id = ? AND qbo_account_id = ?
                        ");
                        $stmt->execute([1, $account->Id]);

                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($existing) {
                            $updateStmt = $this->pdo->prepare("
                                UPDATE qbo_bank SET
                                    name = ?,
                                    account_type = ?,
                                    account_sub_type = ?,
                                    current_balance = ?,
                                    sync_token = ?,
                                    status = 'synced',
                                    error_message = NULL,
                                    updated_at = CURRENT_TIMESTAMP
                                WHERE qbo_company_id = ? AND qbo_account_id = ?
                            ");

                            $updateStmt->execute([
                                $account->Name,
                                $account->AccountType ?? null,
                                $account->AccountSubType ?? null,
                                $account->CurrentBalance ?? 0.00,
                                $account->SyncToken ?? null,
                                1,
                                $account->Id
                            ]);

                            $results[] = [
                                'qbo_account_id' => $account->Id,
                                'name'           => $account->Name,
                                'action'         => 'updated',
                                'success'        => true
                            ];
                        } else {
                            $insertStmt = $this->pdo->prepare("
                                INSERT INTO qbo_bank (
                                    qbo_company_id, qbo_account_id, name, account_type, 
                                    account_sub_type, current_balance, sync_token, status
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'synced')
                            ");

                            $insertStmt->execute([
                                1,
                                $account->Id,
                                $account->Name,
                                $account->AccountType ?? null,
                                $account->AccountSubType ?? null,
                                $account->CurrentBalance ?? 0.00,
                                $account->SyncToken ?? null
                            ]);

                            $results[] = [
                                'qbo_account_id' => $account->Id,
                                'name'           => $account->Name,
                                'action'         => 'created',
                                'success'        => true
                            ];
                        }

                        $success_count++;
                    } catch (\Exception $e) {
                        $results[] = [
                            'qbo_account_id' => $account->Id ?? 'unknown',
                            'name'           => $account->Name ?? 'unknown',
                            'action'         => 'failed',
                            'success'        => false,
                            'error'          => $e->getMessage()
                        ];
                        $error_count++;
                    }
                }
            }

            return [
                'success' => true,
                'message' => "Synced bank accounts. Success: $success_count, Errors: $error_count",
                'results' => $results,
                'summary' => [
                    'total'   => count($results),
                    'success' => $success_count,
                    'errors'  => $error_count
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Bank account sync failed: ' . $e->getMessage(),
                'results' => []
            ];
        }
    }

    /**
     * -------------------------
     * Items (Products / Services / Categories)
     * -------------------------
     */

    /**
     * Create an Item locally and sync to QuickBooks Online.
     */
    public function createItem(array $data): object
    {
        if (empty($data['qbo_company_id'])) {
            throw new \InvalidArgumentException('qbo_company_id is required');
        }

        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Item name is required');
        }

        $itemRepo  = new QboItemRepository($this->pdo);
        $classRepo = new QboClassRepository($this->pdo);

        $client = new ItemClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $service = new ItemSyncService(
            $itemRepo,
            $classRepo,
            $client
        );

        return $service->create($data);
    }

    /**
     * Retrieve a single Item from QuickBooks by QBO ID.
     */
    public function getItem(string $id): object
    {
        $client = new ItemClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        return $client->getById($id);
    }

    /**
     * Retrieve all Items from QuickBooks Online.
     */
    public function getAllItems(int $maxResults = 1000, int $startPosition = 1): object
    {
        $client = new ItemClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        return $client->getAll($maxResults, $startPosition);
    }

    /**
     * Retrieve all Items of a specific type from QuickBooks Online.
     * Types: Service, Inventory, NonInventory, Category, Group, Fixed Asset
     */
    public function getItemsByType(string $type, int $maxResults = 1000, int $startPosition = 1): object
    {
        $client = new ItemClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        return $client->getAllByType($type, $maxResults, $startPosition);
    }

    /**
     * Search for an Item by name in QuickBooks Online.
     */
    public function searchItem(string $name): object
    {
        $client = new ItemClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        return $client->searchByName($name);
    }

    /**
     * Deactivate an Item in QuickBooks Online (soft delete).
     */
    public function deactivateItem(string $qboId, string $syncToken): object
    {
        $client = new ItemClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        return $client->deactivate($qboId, $syncToken);
    }

    /**
     * Pull all Items from QuickBooks Online and store locally.
     * Run during setup or periodically to keep local items in sync.
     */
    public function syncItems(int $qboCompanyId): array
    {
        $itemRepo  = new QboItemRepository($this->pdo);
        $classRepo = new QboClassRepository($this->pdo);

        $client = new ItemClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        $service = new ItemSyncService(
            $itemRepo,
            $classRepo,
            $client
        );

        return $service->sync($qboCompanyId);
    }

    /**
     * Update an existing Item in QuickBooks Online (sparse update).
     */
    public function updateItem(string $qboId, string $syncToken, array $data): object
    {
        $client = new ItemClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        return $client->update($qboId, $syncToken, $data);
    }
    /**
     * Get invoices for a specific customer (by QBO ID)
     */
    public function getInvoicesByCustomer(string $customerQboId): object
    {
        $client = new InvoiceClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        return $client->getByCustomer($customerQboId);
    }
    /**
     * Get invoices with outstanding balances for a specific customer (by QBO ID)
     */
    public function getInvoicesWithBalanceByCustomer(string $customerQboId): object
    {
        $client = new InvoiceClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        return $client->getByCustomerWithBalance($customerQboId);
    }

    /**
     * Get all payments for a specific customer (by QBO ID)
     */
    public function getPaymentsByCustomer(string $customerQboId): object
    {
        $client = new PaymentClient(
            $this->baseUrl,
            $this->companyId,
            $this->auth,
            $this->http
        );

        return $client->getByCustomer($customerQboId);
    }

    /**
     * Get all synced customers
     */
    public function getSyncedCustomers(int $qboCompanyId = 1): array
    {
        $repo = new QboCustomerRepository($this->pdo);
        return $repo->getAllSynced($qboCompanyId);
    }
}
