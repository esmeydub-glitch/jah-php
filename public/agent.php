<?php

declare(strict_types=1);

$boot = require dirname(__DIR__) . '/app/bootstrap.php';
$config = $boot['config'];

use Jah\Memory\TieredMemory;

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = [];

if ($method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $rawBody = file_get_contents('php://input');
        $decoded = $rawBody ? json_decode($rawBody, true) : [];
        $input = is_array($decoded) ? $decoded : [];
    } else {
        $input = $_POST;
    }
} else {
    $input = $_GET;
}

$userMessage = trim((string)($input['message'] ?? ''));
if ($userMessage === '') {
    http_response_code(400);
    echo json_encode(['error' => 'No message provided.'], JSON_UNESCAPED_UNICODE);
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
$tiered->close();

echo json_encode([
    'status' => 'success',
    'response' => $result['response'],
    'model' => $model,
    'context_used' => $result['context_used'],
    'context_preview' => $result['context_preview'],
    'memories' => $result['memories'],
    'classification' => $result['classification'] ?? [],
    'stored' => $result['stored'] ?? [],
    'actions_trace' => $result['actions_trace'],
], JSON_UNESCAPED_UNICODE);
