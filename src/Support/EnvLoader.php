<?php
namespace ExcelleInsights\QuickBooks\Support;

use Dotenv\Dotenv;

final class EnvLoader
{
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        // Priority order:
        // 1. Project root .env (recommended)
        // 2. Package .env (fallback)

        $projectEnv = getcwd() . '/.env';
        $packageEnv = dirname(__DIR__, 2) . '/.env';

        if (file_exists($projectEnv)) {
            Dotenv::createImmutable(getcwd())->safeLoad();
        } elseif (file_exists($packageEnv)) {
            Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();
        }

        self::$loaded = true;
    }
}
