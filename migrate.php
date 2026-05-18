#!/usr/bin/env php
<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use ExcelleInsights\QuickBooks\Support\EnvLoader;

require_once 'vendor/autoload.php';

/**
 * ------------------------------------------------------------
 * 0️⃣ Resolve project root
 * ------------------------------------------------------------
 */
$options = getopt('', ['debug']);

$rootPath = realpath(dirname(__DIR__, 3));
if ($rootPath === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

$projectRoot = isset($options['debug'])
    ? __DIR__ . '/'
    : rtrim($rootPath, '/') . '/';
$envFile = $projectRoot . '.env';
$migrationsDir = isset($options['debug']) ? $projectRoot . 'database/migrations' : $projectRoot . 'vendor/excelle-insights/quickbooks/database/migrations';

/**
 * ------------------------------------------------------------
 * Helper to safely run processes cross-platform
 * ------------------------------------------------------------
 */
function runProcess(Process $process): void
{
    $process->setTimeout(null);

    if (Process::isTtySupported() && PHP_SAPI === 'cli') {
        $process->setTty(true);
    }

    $process->run(function ($type, $buffer) {
        echo $buffer;
    });

    if (!$process->isSuccessful()) {
        throw new RuntimeException(
            trim($process->getErrorOutput() ?: $process->getOutput())
        );
    }
}

/**
 * ------------------------------------------------------------
 * 1️⃣ Load DB config
 * ------------------------------------------------------------
 */


if (!file_exists($envFile)) {
    fwrite(STDERR, "Database config not found (.env missing).\n");
    exit(1);
}

EnvLoader::load($envFile);

$host     = $_ENV['DB_HOST']     ?? null;
$dbname   = $_ENV['DB_NAME']     ?? null;
$user     = $_ENV['DB_USER']     ?? null;
$password = $_ENV['DB_PASSWORD'] ?? '';
$driver   = $_ENV['DB_DRIVER']   ?? 'mysql';

if (!$host || !$dbname || !$user) {
    fwrite(STDERR, "Database config incomplete.\n");
    exit(1);
}

/**
 * ------------------------------------------------------------
 * 2️⃣ Ensure Phinx exists
 * ------------------------------------------------------------
 */
$phinxPath = file_exists($projectRoot . 'vendor/bin/phinx')
    ? $projectRoot . 'vendor/bin/phinx'
    : $projectRoot . 'vendor/bin/phinx.bat';

if (!file_exists($phinxPath)) {
    echo "Phinx not found. Installing...\n";

    try {
        runProcess(new Process(
            ['composer', 'require', '--dev', 'robmorgan/phinx:^0.14'],
            $projectRoot
        ));
        echo "Phinx installed successfully.\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Failed to install Phinx:\n{$e->getMessage()}\n");
        exit(1);
    }
}

/**
 * ------------------------------------------------------------
 * 3️⃣ Generate temporary Phinx config
 * ------------------------------------------------------------
 */
$prefix = $_ENV['QBO_TABLE_PREFIX'] ?? 'qbo';
$tempConfig = sys_get_temp_dir() . '/quickbooks_phinx_' . uniqid() . '.php';

file_put_contents($tempConfig, <<<PHP
<?php
\$_ENV['QBO_TABLE_PREFIX'] = '{$prefix}';
return [
    'paths' => [
        'migrations' => '{$migrationsDir}',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => '{$driver}',
            'host' => '{$host}',
            'name' => '{$dbname}',
            'user' => '{$user}',
            'pass' => '{$password}',
            'port' => '3306',
            'charset' => 'utf8mb4',
        ],
    ],
    'version_order' => 'creation',
];
PHP
);

/**
 * ------------------------------------------------------------
 * 4️⃣ Run migrations
 * ------------------------------------------------------------
 */
try {
    runProcess(new Process(
        [$phinxPath, 'migrate', '-c', $tempConfig],
        $projectRoot
    ));

    echo "✅ QuickBooks migrations ran successfully!\n";
} catch (Throwable $e) {
    fwrite(STDERR, "❌ Migrations failed:\n{$e->getMessage()}\n");
    unlink($tempConfig);
    exit(1);
}

unlink($tempConfig);
