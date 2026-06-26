<?php

declare(strict_types=1);

require_once __DIR__ . '/core/Autoloader.php';

Autoloader::register();
Autoloader::addNamespace('Jah\\Core\\',    __DIR__ . '/core');
Autoloader::addNamespace('Jah\\Agents\\',  __DIR__ . '/agents');
Autoloader::addNamespace('Jah\\Memory\\',  __DIR__ . '/memory');
Autoloader::addNamespace('Jah\\Network\\', __DIR__ . '/network');
Autoloader::addNamespace('Jah\\Cache\\',   __DIR__ . '/cache');

use Jah\Memory\TieredMemory;

$configFile = __DIR__ . '/config/config.php';
if (!is_file($configFile)) {
    die("Error: Config file not found.\n");
}

$config = require $configFile;

$memoryBasePath = $config['paths']['tiered_memory'] ?? __DIR__ . '/memory/tiers';
$tierConfig = $config['tiered_memory_config'] ?? [];

$tieredMemory = new TieredMemory($memoryBasePath, $tierConfig);

if ($argc < 2) {
    echo "Jah Memory Tier Migration Script\n";
    echo "Usage: php migrate_tiers.php [migrate|stats|search] [args]\n\n";
    echo "Commands:\n";
    echo "  migrate    - Run tier migration (move old memories to lower tiers)\n";
    echo "  stats      - Show tier statistics\n";
    echo "  search     - Search memories (args: query [tier])\n";
    exit(1);
}

$command = $argv[1];

switch ($command) {
    case 'migrate':
        echo "Running tier migration...\n";
        $migrated = $tieredMemory->migrateTiers();

        if (empty($migrated)) {
            echo "No memories migrated.\n";
        } else {
            echo "Migrated " . count($migrated) . " memories:\n";
            foreach ($migrated as $m) {
                echo "  - {$m['key']}: {$m['from']} → {$m['to']}\n";
            }
        }
        break;

    case 'stats':
        $stats = $tieredMemory->getStats();
        echo "Jah Memory Tier Statistics\n";
        echo str_repeat('-', 40) . "\n";
        foreach ($stats as $tier => $s) {
            echo strtoupper($tier) . ":\n";
            echo "  Files:    {$s['count']} / {$s['max_files']}\n";
            echo "  Size:     " . round($s['total_size_bytes'] / 1024, 1) . " KB\n";
            echo "  TTL:      " . round($s['ttl_seconds'] / 3600, 1) . " hours\n";
        }
        break;

    case 'search':
        $query = $argv[2] ?? '';
        if (empty($query)) {
            echo "Error: Search query required.\n";
            exit(1);
        }
        $tier = $argv[3] ?? '';
        $tiers = $tier ? [$tier] : ['hot', 'warm', 'cold'];

        $results = $tieredMemory->search($query, $tiers, 20);
        echo "Search results for '{$query}' (" . count($results) . " found):\n";
        echo str_repeat('-', 60) . "\n";

        foreach ($results as $r) {
            echo "[{$r['tier']}] {$r['key']} (score: {$r['score']})\n";
            if (!empty($r['data'])) {
                $preview = json_encode($r['data']);
                echo "  " . substr($preview, 0, 80) . (strlen($preview) > 80 ? '...' : '') . "\n";
            }
            echo "\n";
        }
        break;

    default:
        echo "Unknown command: {$command}\n";
        exit(1);
}
