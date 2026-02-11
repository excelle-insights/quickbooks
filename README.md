# Excelle Insights QuickBooks Integration

A PHP package to integrate with **QuickBooks Online (QBO)**, including authentication, customer management, and local synchronization.

---

## Installation

You can install the package via Composer:

```bash
composer require excelle-insights/quickbooks:dev-main
```

## Environment Setup

Create a .env file in your project root (or rely on the package .env):

```
# QuickBooks API
QBO_BASE_URL=https://quickbooks.api.intuit.com
QBO_CLIENT_ID=YOUR_CLIENT_ID
QBO_CLIENT_SECRET=YOUR_CLIENT_SECRET
QBO_REALM_ID=YOUR_COMPANY_ID
QBO_REDIRECT_URI=http://gimco.local/admin/debug_scripts/qbo-auth-callback.php
QBO_TABLE_PREFIX=qbo_beta

# Database connection
DB_DSN=mysql:host=127.0.0.1;dbname=myapp
DB_USER=root
DB_PASSWORD=secret

```
The package automatically loads its own .env if present.

## Running Migrations

This package uses Phinx for database migrations.

1. Run the migrations:

```bash
php vendor/excelle-insights/quickbooks/migrate.php
```

Tables created include:

- api_access_tokens
- qbo_companies
- qbo_customers
- qbo_invoices
- qbo_invoice_items
- http_request_logs

## Quick Start
1. Get the OAuth Authorization URL

This script generates a link for the user to authorize your app in QuickBooks:

```php
<?php
require '../../vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;
use ExcelleInsights\QuickBooks\Controller\OAuthController;

// Initialize
$qbo = new QuickBooksManager(null, null, dirname(__DIR__, 2));
$oauth = new OAuthController($qbo);

// Get QuickBooks authorization URL
echo $qbo->getAuthUrl();
```

2. Handle OAuth Callback

After the user authorizes your app, QuickBooks redirects back to your callback URL. This script handles that callback:

```php
<?php
require '../../vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;
use ExcelleInsights\QuickBooks\Controller\OAuthController;

$qbo = new QuickBooksManager();
$oauth = new OAuthController($qbo);

// Display result of OAuth callback
echo $oauth->handleCallback();
```

3. Create a Customer

This script creates a customer locally and attempts to sync it with QuickBooks Online:

```php
<?php
require '../../vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

// Initialize the manager
$qbo = new QuickBooksManager();

$index = 1;

$result = $qbo->createCustomer([
    'qbo_company_id' => 1,
    'name'  => 'Test Customer'.$index,
    'email'         => 'testcustomer'.$index.'@email.com',
    'phone'         => '+254724565654'.$index,
    'company_name' => 'Test Company '.$index,
    'country'      => 'Kenya',
    'city'         => 'Nairobi',
    'postal_code'  => '00100',
    'line'         => 'Ngong Road',
]);

if ($result->status === 'synced') {
    echo "Customer synced with QBO ID: " . $result->qbo_id;
} else {
    echo "Customer queued for retry. Local ID: " . $result->local_id;
}

```

4. Create an Invoice

This script creates an invoice locally and attempts to sync it with QuickBooks Online:

```php
<?php
require '../../vendor/autoload.php';

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

// Initialize the manager
$qbo = new QuickBooksManager();

// Create an invoice
$result = $qbo->createInvoice([
    'qbo_company_id'  => 1,
    'qbo_customer_id' => 11, // Replace with actual QBO customer ID
    'invoice_number'  => 'INV-001',
    'txn_date'        => '2026-01-22',
    'due_date'        => '2026-02-05',
    'currency'        => 'KES',
    'items' => [
        [
            'description' => 'Consulting Services',
            'quantity'    => 1,
            'unit_price'  => 5000,
            'amount'      => 5000, // quantity * unit_price
        ],
        [
            'description' => 'Software License',
            'quantity'    => 2,
            'unit_price'  => 1500,
            'amount'      => 3000,
        ],
    ],
]);

if ($result->status === 'synced') {
    echo "Invoice synced with QBO ID: " . $result->qbo_id;
} else {
    echo "Invoice queued for retry. Local ID: " . $result->local_id;
    if (!empty($result->error)) {
        echo "\nError: " . $result->error;
    }
}

```
## Testing

The package uses PHPUnit for testing. To run tests:

```
vendor/bin/phpunit tests
```

Ensure your .env (or package .env) is configured with valid database and QBO credentials.

## Features

- Automatic OAuth2 authentication with QuickBooks Online.
- Local persistence of customers in qbo_customers table.
- Queue system for failed QuickBooks syncs.
- Easy access to QuickBooks API via QuickBooksManager.
- Extensible for invoices, payments, and other QBO objects.

## Example Workflow

- Initialize QuickBooksManager.
- Redirect user to QuickBooks authorization page using getAuthUrl().
- Handle the callback via OAuthController.
- Create customers locally and sync with QBO using createCustomer().
- Create invoices locally and sync with QBO using createInvoice().
