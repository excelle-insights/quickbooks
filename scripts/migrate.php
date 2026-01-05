#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__, 4) . '/vendor/autoload.php';
use Symfony\Component\Process\Process;

$projectRoot = realpath(dirname(__DIR__, 4));
$dbConfigFile = $projectRoot . '/config/env/.database.json';

// 1️⃣ Load DB config
if (!file_exists($dbConfigFile)) {
    echo "Database config not found.\n";
    exit(1);
}

$settings = json_decode(file_get_contents($dbConfigFile));
$host = $settings->host ?? '';
$dbname = $settings->database ?? '';
$user = $settings->user ?? '';
$password = $settings->password ?? '';

if (!$host || !$dbname || !$user) {
    echo "Database config incomplete.\n";
    exit(1);
}

// 2️⃣ Check Phinx
$phinxPath = $projectRoot . '/vendor/bin/phinx';
if (!file_exists($phinxPath)) {
    echo "Phinx not found. Installing...\n";
    $install = new Process(['composer', 'require', '--dev', 'robmorgan/phinx:^0.14'], $projectRoot);
    $install->setTty(true)->run();
    if (!$install->isSuccessful()) exit(1);
    echo "Phinx installed successfully.\n";
}

// 3️⃣ Generate temp Phinx config
$tempConfig = sys_get_temp_dir() . '/quickbooks_phinx.php';
file_put_contents($tempConfig, "<?php
return [
    'paths' => [
        'migrations' => '{$projectRoot}/vendor/excelle-insights/quickbooks/database/migrations',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => '$host',
            'name' => '$dbname',
            'user' => '$user',
            'pass' => '$password',
            'port' => '3306',
            'charset' => 'utf8',
        ]
    ],
    'version_order' => 'creation'
];
");

// 4️⃣ Run migrations
$process = new Process([$phinxPath, 'migrate', '-c', $tempConfig], $projectRoot);
$process->setTty(true)->run();

if (!$process->isSuccessful()) {
    echo "Migrations failed.\n";
    unlink($tempConfig);
    exit(1);
}

echo "QuickBooks migrations ran successfully!\n";
unlink($tempConfig);
