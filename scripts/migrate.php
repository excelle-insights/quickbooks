#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/vendor/autoload.php';

use Symfony\Component\Process\Process;

$projectRoot  = realpath(dirname(__DIR__, 4));
$dbConfigFile = $projectRoot . '/config/env/.database.json';

/**
 * Helper to safely run processes cross-platform
 */
function runProcess(Process $process): void
{
    $process->setTimeout(null);

    if (Process::isTtySupported()) {
        $process->setTty(true);
    }

    $process->run(function ($type, $buffer) {
        echo $buffer;
    });

    if (!$process->isSuccessful()) {
        throw new RuntimeException($process->getErrorOutput());
    }
}

/**
 * 1️⃣ Load DB config
 */
if (!file_exists($dbConfigFile)) {
    echo "Database config not found.\n";
    exit(1);
}

$settings = json_decode(file_get_contents($dbConfigFile));

$host     = $settings->host ?? '';
$dbname   = $settings->database ?? '';
$user     = $settings->user ?? '';
$password = $settings->password ?? '';

if (!$host || !$dbname || !$user) {
    echo "Database config incomplete.\n";
    exit(1);
}

/**
 * 2️⃣ Ensure Phinx exists
 */
$phinxPath = $projectRoot . '/vendor/bin/phinx';

if (!file_exists($phinxPath)) {
    echo "Phinx not found. Installing...\n";

    try {
        runProcess(new Process(
            ['composer', 'require', '--dev', 'robmorgan/phinx:^0.14'],
            $projectRoot
        ));
        echo "Phinx installed successfully.\n";
    } catch (Throwable $e) {
        echo "Failed to install Phinx:\n{$e->getMessage()}\n";
        exit(1);
    }
}

/**
 * 3️⃣ Generate temporary Phinx config
 */
$tempConfig = sys_get_temp_dir() . '/quickbooks_phinx.php';

file_put_contents($tempConfig, <<<PHP
<?php
return [
    'paths' => [
        'migrations' => '{$projectRoot}/vendor/excelle-insights/quickbooks/database/migrations',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => '{$host}',
            'name' => '{$dbname}',
            'user' => '{$user}',
            'pass' => '{$password}',
            'port' => '3306',
            'charset' => 'utf8',
        ],
    ],
    'version_order' => 'creation',
];
PHP
);

/**
 * 4️⃣ Run migrations
 */
try {
    runProcess(new Process(
        [$phinxPath, 'migrate', '-c', $tempConfig],
        $projectRoot
    ));

    echo "QuickBooks migrations ran successfully!\n";
} catch (Throwable $e) {
    echo "Migrations failed:\n{$e->getMessage()}\n";
    unlink($tempConfig);
    exit(1);
}

unlink($tempConfig);
