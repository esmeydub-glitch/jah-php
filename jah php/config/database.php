<?php

declare(strict_types=1);

require_once __DIR__ . '/environment.php';

return [
    'driver' => (string) jah_env('JAH_DB_DRIVER', 'mysql'),
    'host' => (string) jah_env('JAH_DB_HOST', '127.0.0.1'),
    'port' => jah_int_env('JAH_DB_PORT', 3306),
    'database' => (string) jah_env('JAH_DB_NAME', 'jah_motor'),
    'username' => (string) jah_env('JAH_DB_USER', 'jah_kernel'),
    'password' => jah_env('JAH_DB_PASS', null),
    'charset' => (string) jah_env('JAH_DB_CHARSET', 'utf8mb4'),
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
