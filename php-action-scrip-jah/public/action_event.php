<?php

declare(strict_types=1);

require_once __DIR__ . '/../jah php/core/Autoloader.php';

Autoloader::register();
Autoloader::addNamespace('Jah\\Core\\', __DIR__ . '/../jah php/core');
Autoloader::addNamespace('Jah\\Agents\\', __DIR__ . '/../jah php/agents');
Autoloader::addNamespace('Jah\\Memory\\', __DIR__ . '/../jah php/memory');
Autoloader::addNamespace('Jah\\Network\\', __DIR__ . '/../jah php/network');
Autoloader::addNamespace('Jah\\Cache\\', __DIR__ . '/../jah php/cache');
Autoloader::addNamespace('Jah\\Security\\', __DIR__ . '/../jah php/security');

use Jah\Core\JahEngine;

$payload = $_POST['payload'] ?? [];
if (is_string($payload)) {
    $decoded = json_decode($payload, true);
    $payload = is_array($decoded) ? $decoded : [];
}

$event = [
    'event' => (string) ($_POST['event'] ?? ''),
    'component_id' => (string) ($_POST['component_id'] ?? ''),
    'payload' => is_array($payload) ? $payload : [],
    'salk_token' => (string) ($_POST['salk_token'] ?? ''),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'time' => time(),
];

$config = require __DIR__ . '/../jah php/config/config.php';
$config['log']['enabled'] = false;

$engine = JahEngine::getInstance();
$engine->boot($config);
$result = $engine->dispatchSignedEvent($event);
$engine->shutdown();

http_response_code(($result['ok'] ?? false) ? 200 : 403);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
