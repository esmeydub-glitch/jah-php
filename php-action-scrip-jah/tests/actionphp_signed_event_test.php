<?php

require_once __DIR__ . '/../app/ActionPHP/bootstrap.php';
require_once __DIR__ . '/../jah php/core/Autoloader.php';

Autoloader::register();
Autoloader::addNamespace('Jah\\Core\\', __DIR__ . '/../jah php/core');
Autoloader::addNamespace('Jah\\Agents\\', __DIR__ . '/../jah php/agents');
Autoloader::addNamespace('Jah\\Memory\\', __DIR__ . '/../jah php/memory');
Autoloader::addNamespace('Jah\\Network\\', __DIR__ . '/../jah php/network');
Autoloader::addNamespace('Jah\\Cache\\', __DIR__ . '/../jah php/cache');
Autoloader::addNamespace('Jah\\Security\\', __DIR__ . '/../jah php/security');

use Jah\Core\JahEngine;

function fail_signed(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assert_signed(bool $condition, string $message): void
{
    if (!$condition) {
        fail_signed($message);
    }
}

$button = (new JahButton('Reproducir video', 'btn_play'))
    ->onClick('jah.video.play')
    ->salkProtect([
        'event' => 'jah.video.play',
        'process' => 'video.runtime',
        'payload' => ['file' => 'jah-demo.webm'],
    ]);

$html = $button->render();
if (!preg_match('/data-salk-token="([^"]+)"/', $html, $matches)) {
    fail_signed('No se encontro token SALK en el componente.');
}

$token = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
$config = require __DIR__ . '/../jah php/config/config.php';
$config['log']['enabled'] = false;

$engine = JahEngine::getInstance();
$engine->boot($config);

$received = 0;
$engine->getEventBus()->subscribe('jah.video.play', function () use (&$received): void {
    $received++;
});

$valid = $engine->dispatchSignedEvent([
    'event' => 'jah.video.play',
    'component_id' => 'btn_play',
    'payload' => ['file' => 'jah-demo.webm'],
    'salk_token' => $token,
]);

assert_signed($valid['ok'] === true, 'Evento firmado valido debe entrar.');
assert_signed($received === 1, 'Evento firmado valido debe publicarse una vez.');

$wrongPayload = $engine->dispatchSignedEvent([
    'event' => 'jah.video.play',
    'component_id' => 'btn_play',
    'payload' => ['file' => 'otro.webm'],
    'salk_token' => $token,
]);
assert_signed($wrongPayload['ok'] === false && $wrongPayload['error'] === 'SALK_PAYLOAD_MISMATCH', 'Payload alterado debe rechazarse.');

$wrongEvent = $engine->dispatchSignedEvent([
    'event' => 'jah.video.pause',
    'component_id' => 'btn_play',
    'payload' => ['file' => 'jah-demo.webm'],
    'salk_token' => $token,
]);
assert_signed($wrongEvent['ok'] === false && $wrongEvent['error'] === 'SALK_EVENT_MISMATCH', 'Evento alterado debe rechazarse.');

$wrongComponent = $engine->dispatchSignedEvent([
    'event' => 'jah.video.play',
    'component_id' => 'btn_other',
    'payload' => ['file' => 'jah-demo.webm'],
    'salk_token' => $token,
]);
assert_signed($wrongComponent['ok'] === false && $wrongComponent['error'] === 'SALK_COMPONENT_MISMATCH', 'Componente alterado debe rechazarse.');

$missingToken = $engine->dispatchSignedEvent([
    'event' => 'jah.video.play',
    'component_id' => 'btn_play',
    'payload' => ['file' => 'jah-demo.webm'],
    'salk_token' => '',
]);
assert_signed($missingToken['ok'] === false && $missingToken['error'] === 'SALK_TOKEN_MISSING', 'Evento sin firma debe rechazarse.');

$engine->shutdown();

echo "ActionPHP signed event tests PASS\n";
