<?php

declare(strict_types=1);

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
use Jah\DataCore\DataCoreTurbo;
use Jah\DataCore\MemoryPyramid;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

if (!is_array($input) || !isset($input['message']) || !is_string($input['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No message provided'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

$userMessage = trim($input['message']);

if ($userMessage === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Empty message'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

$configFile = $basePath . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Config file not found'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

$config = require $configFile;

$storagePath = $config['paths']['datacore_storage'] ?? $basePath . '/memory/datacore';
$hotStoragePath = $config['paths']['hot_storage'] ?? $basePath . '/memory/pyramid';

$turbo = new DataCoreTurbo($storagePath, 500);
$memoryPyramid = new MemoryPyramid($hotStoragePath);

$collection = $input['collection'] ?? 'memories';

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

date_default_timezone_set('America/Mexico_City');
$currentDate = date('Y-m-d H:i:s T');
$systemContext = "Fecha y hora actual: $currentDate\nDirectorio de trabajo: {$basePath}\n";
if (!empty($contextString)) {
    $contextString = $systemContext . "Contexto de memoria:\n" . $contextString;
} else {
    $contextString = $systemContext . "No hay contexto de memoria previo.";
}

$qwenConfigFile = $basePath . '/config/qwen.php';
$configApiKey = '';
$configModel = 'qwen-max';
if (is_file($qwenConfigFile) && is_readable($qwenConfigFile)) {
    $qwenConfig = require $qwenConfigFile;
    if (is_array($qwenConfig)) {
        $configApiKey = $qwenConfig["api_key"] ?? "";
        $configModel = $qwenConfig['model'] ?? 'qwen-max';
    }
}

$envApiKey = $_ENV['QWEN_API_KEY'] ?? getenv('QWEN_API_KEY') ?? '';
if ($envApiKey === false) {
    $envApiKey = '';
}
$apiKey = $envApiKey !== '' ? $envApiKey : $configApiKey;
$model = $configModel;

if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(['error' => 'QWEN_API_KEY not configured. Set QWEN_API_KEY environment variable or edit config/qwen.php.'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $turbo->close();
    exit;
}

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

echo json_encode([
    'status' => 'success',
    'response' => $response,
    'context_used' => count($hotResults),
    'model' => $model,
    'memory_ids' => ['user' => $userMemoryId, 'assistant' => $assistantMemoryId],
], JSON_UNESCAPED_UNICODE);
