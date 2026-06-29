<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$envFile = $root . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, '"\'');
            $_ENV[$key] = $val;
            putenv("{$key}={$val}");
        }
    }
}

require_once __DIR__ . '/core/Autoloader.php';

Autoloader::register();
Autoloader::addNamespace('Jah\\Core\\', __DIR__ . '/core');
Autoloader::addNamespace('Jah\\Agents\\', __DIR__ . '/agents');
Autoloader::addNamespace('Jah\\Memory\\', __DIR__ . '/memory');
Autoloader::addNamespace('Jah\\Network\\', __DIR__ . '/network');
Autoloader::addNamespace('Jah\\Cache\\', __DIR__ . '/cache');
Autoloader::addNamespace('Jah\\Security\\', __DIR__ . '/security');
Autoloader::addNamespace('Jah\\Http\\', __DIR__ . '/http');
Autoloader::addNamespace('Jah\\DataCore\\', $root . '/src/DataCore');

require_once __DIR__ . '/QwenConnector.php';

$config = require __DIR__ . '/config/config.php';

foreach (['datacore_storage', 'hot_storage', 'logs', 'tmp', 'cache'] as $pathKey) {
    if (isset($config['paths'][$pathKey]) && !str_starts_with($config['paths'][$pathKey], '/')) {
        $config['paths'][$pathKey] = $root . '/' . $config['paths'][$pathKey];
    }
}

return [
    'root' => $root,
    'app' => __DIR__,
    'config' => $config,
];
