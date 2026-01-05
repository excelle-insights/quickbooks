<?php
namespace ExcelleInsights\QuickBooks\Support;

use Dotenv\Dotenv;

class EnvLoader
{
    public static function load(): void
    {
        // Only load once
        if (!defined('QBO_ENV_LOADED')) {
            $dir = dirname(__DIR__, 1); // path to package root
            $dotenv = Dotenv::createImmutable($dir);
            $dotenv->load();
            define('QBO_ENV_LOADED', true);
        }
    }
}
