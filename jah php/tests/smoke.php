<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Autoloader.php';

Autoloader::register();
Autoloader::addNamespace('Jah\\Core\\', __DIR__ . '/../core');
Autoloader::addNamespace('Jah\\Agents\\', __DIR__ . '/../agents');
Autoloader::addNamespace('Jah\\Memory\\', __DIR__ . '/../memory');
Autoloader::addNamespace('Jah\\Network\\', __DIR__ . '/../network');
Autoloader::addNamespace('Jah\\Cache\\', __DIR__ . '/../cache');

use Jah\Cache\CacheManager;
use Jah\Core\JahEngine;

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fail($message);
    }
}

$config = require __DIR__ . '/../config/config.php';
$config['log']['enabled'] = false;

$engine = JahEngine::getInstance();
$engine->boot($config);

$bus = $engine->getEventBus();
$eventCount = 0;
$bus->subscribe('smoke.ok', function () use (&$eventCount): void {
    $eventCount++;
});
$bus->publish('smoke.ok');
assert_true($eventCount === 1, 'EventBus debe entregar un evento una sola vez.');

$gateway = $engine->getAgent('GatewayAgent');
assert_true($gateway instanceof Jah\Agents\GatewayAgent, 'GatewayAgent debe estar registrado.');

$validatedCount = 0;
$bus->subscribe('gateway.validated', function () use (&$validatedCount): void {
    $validatedCount++;
});
$gateway->handleCliRequest(['jah', 'test', 'param1=valor']);
assert_true($validatedCount === 1, 'Gateway no debe procesar eventos duplicados.');

$cache = new CacheManager($config['paths']['cache']);
$cache->clear();
assert_true($cache->set('smoke_key', ['ok' => true], 60), 'CacheManager debe escribir correctamente.');
assert_true($cache->get('smoke_key') === ['ok' => true], 'CacheManager debe recuperar valores PHP.');
assert_true(count(glob($config['paths']['cache'] . '/*.php')) >= 1, 'CacheManager debe usar archivos PHP.');
assert_true(count(glob($config['paths']['cache'] . '/*.json')) === 0, 'No debe existir caché JSON.');
$cache->delete('smoke_key');

$engine->shutdown();

echo 'Smoke tests passed.' . PHP_EOL;
