<?php
/**
 * Cron entry point for Jah memory tier migration.
 * Run via: php /path/to/jah/php/cron_tier_migration.php
 * Or via system cron: */5 * * * * php /path/to/jah-php/cron_tier_migration.php
 */

declare(strict_types=1);

require_once __DIR__ . '/core/Autoloader.php';

Autoloader::register();
Autoloader::addNamespace('Jah\Memory\\', __DIR__ . '/memory');

use Jah\Memory\TieredMemory;

$config = require __DIR__ . '/config/config.php';

$memoryBasePath = $config['paths']['tiered_memory'] ?? __DIR__ . '/memory/tiers';
$tierConfig = $config['tiered_memory_config'] ?? [];

$tieredMemory = new TieredMemory($memoryBasePath, $tierConfig);

$logFile = $config['paths']['logs'] . '/migration.log';

$timestamp = date('Y-m-d H:i:s');
$memory = "[" . $timestamp . "] Running tier migration...\n";
file_put_contents($logFile, $memory, FILE_APPEND);

$migrated = $tieredMemory->migrateTiers();

$count = count($migrated);
$memory = "[" . $timestamp . "] Migrated {$count} memories.\n";

if ($count > 0) {
    foreach ($migrated as $m) {
        $memory .= "  - {$m['key']}: {$m['from']} → {$m['to']}\n";
    }
}

file_put_contents($logFile, $memory, FILE_APPEND);
