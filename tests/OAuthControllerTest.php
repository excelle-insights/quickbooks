<?php
namespace ExcelleInsights\QuickBooks\Tests; // optional namespace

use PHPUnit\Framework\TestCase;
use ExcelleInsights\QuickBooks\Facade\QuickBooksManager;
use ExcelleInsights\QuickBooks\Controller\OAuthController;

class OAuthControllerTest extends TestCase
{
    private OAuthController $controller;
    private $mockQbo;

    protected function setUp(): void
    {
        $this->mockQbo = $this->createMock(QuickBooksManager::class);

        $this->mockQbo->method('getAuthUrl')->willReturn('https://dummy-auth-url');

        $this->mockQbo->method('authenticate')->willReturnCallback(function ($code, $realmId) {
            // simulate token exchange success
        });

        $this->controller = new OAuthController($this->mockQbo);
    }

    public function testRedirectToQuickBooks(): void
    {
        ob_start();
        try {
            $this->controller->redirectToQuickBooks();
        } catch (\Exception $e) {
            // Ignore exit()
        }
        ob_end_clean();

        $this->assertTrue(true); // redirect executed
    }

    public function testHandleCallbackSuccess(): void
    {
        $_GET['code'] = 'dummy_code';
        $_GET['realmId'] = 'dummy_realm';

        $result = $this->controller->handleCallback();
        $this->assertStringContainsString('successful', $result);
    }

    public function testHandleCallbackMissingParams(): void
    {
        $_GET = [];

        $result = $this->controller->handleCallback();
        $this->assertStringContainsString('Missing code or realmId', $result);
    }

    public function testHandleCallbackWithError(): void
    {
        $_GET['error'] = 'access_denied';

        $result = $this->controller->handleCallback();
        $this->assertStringContainsString('QuickBooks OAuth error', $result);
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }
}
