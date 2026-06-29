<?php

declare(strict_types=1);

$boot = require dirname(__DIR__) . '/app/bootstrap.php';
$config = $boot['config'];

use Jah\Memory\TieredMemory;
use Jah\Http\JahTransport;

header('Content-Type: text/plain; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$input = JahTransport::decodeRequest((int)($config['security']['max_payload_bytes'] ?? 1048576));

$userMessage = trim((string)($input['message'] ?? ''));
if ($userMessage === '') {
    JahTransport::respond(['status' => 'error', 'error' => 'No message provided.'], null, 400);
    exit;
}

$collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($input['collection'] ?? 'memories')) ?: 'memories';
$model = (string)($input['model'] ?? $config['qwen']['model'] ?? getenv('QWEN_MODEL') ?: 'qwen-max');

$storagePath = (string)$config['paths']['datacore_storage'];
$hotStoragePath = (string)$config['paths']['hot_storage'];

$tiered = new TieredMemory($storagePath, $hotStoragePath);
require_once dirname(__DIR__) . '/app/actions/MemoryActionScript.php';
$runtime = new MemoryActionScript($tiered, $config);

$result = $runtime->runAgent($userMessage, $collection, $model);

$output = [
    'status' => 'success',
    'response' => $result['response'],
    'model' => $model,
    'context_used' => $result['context_used'],
    'context_preview' => $result['context_preview'],
    'memories' => $result['memories'],
    'classification' => $result['classification'] ?? [],
    'stored' => $result['stored'] ?? [],
    'actions_trace' => $result['actions_trace'],
];

$tiered->close();
JahTransport::respond($output, $runtime->getSalkGuard());
