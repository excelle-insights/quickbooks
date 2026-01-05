<?php
namespace ExcelleInsights\QuickBooks;

use ExcelleInsights\QuickBooks\Support\PackageEnvLoader;

final class QuickBooks
{
    public static function boot(): void
    {
        PackageEnvLoader::load();
    }
}
