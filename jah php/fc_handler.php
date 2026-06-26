<?php

declare(strict_types=1);

/**
 * Alibaba Cloud Function Compute Handler.
 *
 * This file acts as the entry point for Function Compute.
 * Upload this as index.php in your Function Compute service.
 *
 * Function Compute passes events via POST body.
 * Format: {"message": "user question", "collection": "memories"}
 */

$basePath = __DIR__;

require_once $basePath . '/core/Autoloader.php';
require_once $basePath . '/QwenConnector.php';

Autoloader::register();
Autoloader::addNamespace('Jah\\Core\\',       $basePath . '/core');
Autoloader::addNamespace('Jah\\Agents\\',     $basePath . '/agents');
Autoloader::addNamespace('Jah\\Memory\\',     $basePath . '/memory');
Autoloader::addNamespace('Jah\\DataCore\\',   dirname($basePath) . '/jah-datacore/src');
Autoloader::addNamespace('Jah\\Network\\',    $basePath . '/network');
Autoloader::addNamespace('Jah\\Cache\\',      $basePath . '/cache');
use Jah\Core\JahEngine;
use Jah\DataCore\DataCoreTurbo;
use Jah\DataCore\MemoryPyramid;

header('Content-Type: application/json; charset=utf-8');

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true, 512, JSON_THROW_ON_ERROR);

if (!is_array($input) || !isset($input['message'])) {
    echo json_encode(['error' => 'No message provided'], JSON_THROW_ON_ERROR);
    exit;
}

$userMessage = trim($input['message']);
$collection = $input['collection'] ?? 'memories';

if ($userMessage === '') {
    echo json_encode(['error' => 'Empty message'], JSON_THROW_ON_ERROR);
    exit;
}

$configFile = $basePath . '/config/config.php';
$config = is_file($configFile) ? require $configFile : [];

$engine = JahEngine::getInstance();
$engine->boot($config);

$storagePath = $config['paths']['datacore_storage'] ?? $basePath . '/memory/datacore';
$hotStoragePath = $config['paths']['hot_storage'] ?? $basePath . '/memory/pyramid';

$turbo = new DataCoreTurbo($storagePath, 500);
$memoryPyramid = new MemoryPyramid($hotStoragePath);

$queryLower = strtolower($userMessage);
$queryTerms = array_filter(explode(' ', $queryLower));

$hotResults = $turbo->query($collection, function (array $doc) use ($queryTerms, $queryLower) {
    if (empty($doc) || ($doc['_deleted'] ?? false) === true) {
        return false;
    }
    $searchable = strtolower(json_encode($doc));
    foreach ($queryTerms as $term) {
        if (str_contains($searchable, $term)) {
            return true;
        }
    }
    return str_contains($searchable, $queryLower);
});

$contextItems = [];
foreach ($hotResults as $item) {
    $content = $item['content'] ?? ($item['query'] ?? json_encode($item));
    if (is_array($content)) {
        $content = json_encode($content);
    }
    $contextItems[] = '- ' . substr((string) $content, 0, 200);
}
$contextString = implode("\n", array_slice($contextItems, 0, 10));

$apiKey = $_ENV['QWEN_API_KEY'] ?? getenv('QWEN_API_KEY') ?? '';

if (empty($apiKey)) {
    $qwenConfigFile = $basePath . '/config/qwen.php';
    if (is_file($qwenConfigFile)) {
        $qwenConfig = require $qwenConfigFile;
        $apiKey = $qwenConfig['api_key'] ?? '';
    }
}

if (empty($apiKey)) {
    echo json_encode(['error' => 'QWEN_API_KEY not configured. Set QWEN_API_KEY environment variable.'], JSON_THROW_ON_ERROR);
    $turbo->close();
    $engine->shutdown();
    exit;
}

$model = $config['qwen']['model'] ?? 'qwen-max';
$qwen = new QwenConnector($apiKey);
$response = $qwen->chat($userMessage, $contextString, $model);

$now = time();
$userMemoryId = 'user_' . bin2hex(random_bytes(6));
$assistantMemoryId = 'assistant_' . bin2hex(random_bytes(6));

$turbo->insert($collection, [
    'id' => $userMemoryId,
    'role' => 'user',
    'content' => $userMessage,
    'tags' => ['conversation', 'user_input'],
    '_ts' => $now,
    '_tier' => 'hot',
]);

$turbo->insert($collection, [
    'id' => $assistantMemoryId,
    'role' => 'assistant',
    'content' => $response,
    'tags' => ['conversation', 'assistant_response'],
    '_ts' => $now,
    '_tier' => 'hot',
]);

$memoryPyramid->set($userMemoryId, ['role' => 'user', 'content' => $userMessage]);
$memoryPyramid->set($assistantMemoryId, ['role' => 'assistant', 'content' => $response]);

$turbo->close();
$engine->shutdown();

echo json_encode([
    'status' => 'success',
    'response' => $response,
    'context_used' => count($hotResults),
    'model' => $model,
], JSON_THROW_ON_ERROR);
