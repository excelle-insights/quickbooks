<?php
namespace ExcelleInsights\QuickBooks\Support;

use Dotenv\Dotenv;

final class EnvLoader
{
    private static bool $loaded = false;

    public static function load(?string $rootPath = null): void
    {
        date_default_timezone_set('Africa/Nairobi');
        
        if (self::$loaded) {
            return;
        }

        // 1️⃣ Explicit root (best)
        if ($rootPath && file_exists($rootPath . '/.env')) {
            Dotenv::createImmutable($rootPath)->safeLoad();
            self::$loaded = true;
            return;
        }

        // 2️⃣ Try project root via composer autoload location
        $vendorDir = dirname(__DIR__, 4); // vendor/excelle-insights/quickbooks/src
        $projectRoot = dirname($vendorDir);

        if (file_exists($projectRoot . '/.env')) {
            Dotenv::createImmutable($projectRoot)->safeLoad();
            self::$loaded = true;
            return;
        }

        // 3️⃣ Fallback: package env (optional)
        $packageEnv = dirname(__DIR__, 2) . '/.env';
        if (file_exists($packageEnv)) {
            Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();
            self::$loaded = true;
        }
    }
}
