<?php

declare(strict_types=1);

require_once __DIR__ . '/environment.php';

$qwenConfig = require __DIR__ . '/qwen.php';
$salkConfig = require __DIR__ . '/salk.php';

return [
    'motor' => 'JAH',
    'version' => '1.0.0',
    'qwen' => $qwenConfig,
    'salk' => $salkConfig,
    'env' => (string) jah_env('JAH_ENV', 'production'),
    'timezone' => (string) jah_env('JAH_TIMEZONE', 'UTC'),
    'debug' => (bool) jah_env('JAH_DEBUG', false),
    'agents' => [
        /*
         * Hackathon clean mode:
         * the active runtime is app/actions/MemoryActionScript.php.
         * Legacy coordination agents were removed to avoid confusion.
         */
        'active_runtime' => 'MemoryActionScript',
        'boot_on_start' => [],
    ],
    'paths' => [
        'root' => dirname(__DIR__, 2),
        'logs' => (string) jah_env('JAH_LOG_DIR', dirname(__DIR__, 2) . '/runtime/logs'),
        'tmp' => (string) jah_env('JAH_TMP_DIR', dirname(__DIR__, 2) . '/runtime/tmp'),
        'cache' => (string) jah_env('JAH_CACHE_DIR', dirname(__DIR__, 2) . '/runtime/cache'),
        'datacore_storage' => (string) jah_env('JAH_DATACORE_STORAGE', dirname(__DIR__, 2) . '/runtime/memory/datacore'),
        'hot_storage' => (string) jah_env('JAH_HOT_STORAGE', dirname(__DIR__, 2) . '/runtime/memory/pyramid'),
    ],
    'tiered_memory_config' => [
        'hot' => ['ttl' => 3600, 'max_files' => 1000],
        'warm' => ['ttl' => 86400, 'max_files' => 5000],
        'cold' => ['ttl' => 604800, 'max_files' => 50000],
    ],
    'log' => [
        'enabled' => (bool) jah_env('JAH_LOG_ENABLED', true),
        'level' => (string) jah_env('JAH_LOG_LEVEL', 'warning'),
        'file' => (string) jah_env('JAH_LOG_FILE', dirname(__DIR__) . '/logs/jah.log'),
    ],
    'security' => [
        'allowed_methods' => ['GET', 'POST'],
        'max_action_length' => 80,
        'max_payload_bytes' => 1_048_576,
        'max_cache_ttl' => 86_400,
        'trusted_proxies' => array_filter(array_map('trim', explode(',', (string) jah_env('JAH_TRUSTED_PROXIES', '')))),
    ],
];


