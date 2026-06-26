<?php

declare(strict_types=1);

require_once __DIR__ . '/environment.php';

return [
    'motor' => 'JAH',
    'version' => '1.0.0',
    'env' => (string) jah_env('JAH_ENV', 'production'),
    'timezone' => (string) jah_env('JAH_TIMEZONE', 'UTC'),
    'debug' => (bool) jah_env('JAH_DEBUG', false),
    'agents' => [
        'max_workers' => jah_int_env('JAH_MAX_WORKERS', 4),
        'worker_timeout' => jah_int_env('JAH_WORKER_TIMEOUT', 30),
        'boot_on_start' => [
            'GatewayAgent',
            'MemoryAgent',
            'ObserverAgent',
            'PredictorAgent',
            'OrchestratorAgent',
            'NetworkAgent',
            'WorkerAgent',
            'CacheAgent',
            'ExecutorAgent',
            'AnalystAgent',
            'OptimizerAgent',
            'CleanerAgent',
        ],
    ],
    'paths' => [
        'root' => dirname(__DIR__),
        'logs' => (string) jah_env('JAH_LOG_DIR', dirname(__DIR__) . '/logs'),
        'tmp' => (string) jah_env('JAH_TMP_DIR', dirname(__DIR__) . '/tmp'),
        'cache' => (string) jah_env('JAH_CACHE_DIR', dirname(__DIR__) . '/cache/store'),
        'tiered_memory' => (string) jah_env('JAH_TIERED_MEMORY_DIR', dirname(__DIR__) . '/memory/tiers'),
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


