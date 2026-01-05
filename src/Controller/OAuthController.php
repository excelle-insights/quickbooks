<?php

namespace ExcelleInsights\QuickBooks\Controller;

use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;

class OAuthController
{
    private QuickBooksManager $qbo;

    public function __construct(QuickBooksManager $qbo)
    {
        $this->qbo = $qbo;
    }

    /**
     * Returns the URL to redirect users to QuickBooks for OAuth2.
     */
    public function redirectToQuickBooks(): void
    {
        $authUrl = $this->qbo->getAuthUrl();
        header("Location: $authUrl");
        exit;
    }

    /**
     * Handles the callback from QuickBooks after user authorizes.
     * Stores access token automatically.
     */
    public function handleCallback(): string
    {
        $code = $_GET['code'] ?? null;
        $realmId = $_GET['realmId'] ?? null;
        $error = $_GET['error'] ?? null;

        if ($error) {
            return "QuickBooks OAuth error: " . htmlspecialchars($error);
        }

        if (!$code || !$realmId) {
            return "Missing code or realmId in callback.";
        }

        $this->qbo->authenticate($code, $realmId);

        return "QuickBooks integration successful!";
    }
}
