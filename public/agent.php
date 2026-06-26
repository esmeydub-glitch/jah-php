<?php

declare(strict_types=1);

$boot = require dirname(__DIR__) . '/app/bootstrap.php';

use Jah\DataCore\DataCoreTurbo;
use Jah\DataCore\MemoryPyramid;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$config = $boot['config'];

$input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
$userMessage = trim((string) ($input['message'] ?? ''));

if ($userMessage === '') {
    http_response_code(400);
    echo json_encode(['error' => 'No message provided.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', $input['collection'] ?? 'memories');
$model = (string) ($input['model'] ?? $config['qwen']['model'] ?? 'qwen-max');

$storagePath = (string) $config['paths']['datacore_storage'];
$hotStoragePath = (string) $config['paths']['hot_storage'];

$turbo = new DataCoreTurbo($storagePath, 500);
$memoryPyramid = new MemoryPyramid($hotStoragePath);

$queryLower = mb_strtolower($userMessage);
$queryTerms = array_values(array_filter(preg_split('/\s+/', $queryLower) ?: []));

$memoryResults = $turbo->query($collection, static function (array $doc) use ($queryTerms, $queryLower): bool {
    if (($doc['_deleted'] ?? false) === true) {
        return false;
    }
    $searchable = mb_strtolower(json_encode($doc, JSON_UNESCAPED_UNICODE) ?: '');
    if ($queryLower !== '' && str_contains($searchable, $queryLower)) {
        return true;
    }
    foreach ($queryTerms as $term) {
        if (mb_strlen($term) >= 3 && str_contains($searchable, $term)) {
            return true;
        }
    }
    return false;
});

usort($memoryResults, static fn(array $a, array $b): int => (int) ($b['_ts'] ?? 0) <=> (int) ($a['_ts'] ?? 0));

$contextItems = [];
foreach (array_slice($memoryResults, 0, 10) as $item) {
    $content = $item['content'] ?? json_encode($item, JSON_UNESCAPED_UNICODE);
    if (is_array($content)) {
        $content = json_encode($content, JSON_UNESCAPED_UNICODE);
    }
    $role = (string) ($item['role'] ?? 'memory');
    $contextItems[] = '- [' . $role . '] ' . mb_substr((string) $content, 0, 280);
}

$date = new DateTimeImmutable('now', new DateTimeZone((string) ($config['timezone'] ?? 'America/Mexico_City')));
$context = "Fecha actual: " . $date->format('Y-m-d H:i:s T') . "\n";
$context .= $contextItems === []
    ? "Memorias: ninguna."
    : "Memorias recuperadas:\n" . implode("\n", $contextItems);

$apiKey = (string) ($_ENV['QWEN_API_KEY'] ?? getenv('QWEN_API_KEY') ?? '');
if ($apiKey === '') {
    $turbo->close();
    http_response_code(500);
    echo json_encode(['error' => 'QWEN_API_KEY no configurada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$qwen = new QwenConnector($apiKey);
$response = $qwen->chat($userMessage, $context, $model);

$now = time();
$userMemoryId = 'user_' . bin2hex(random_bytes(6));
$assistantMemoryId = 'assistant_' . bin2hex(random_bytes(6));

$turbo->insert($collection, [
    'id' => $userMemoryId,
    'role' => 'user',
    'content' => $userMessage,
    'tags' => ['conversation'],
    '_ts' => $now,
    '_tier' => 'hot',
]);

$turbo->insert($collection, [
    'id' => $assistantMemoryId,
    'role' => 'assistant',
    'content' => $response,
    'tags' => ['conversation'],
    '_ts' => $now,
    '_tier' => 'hot',
]);

$memoryPyramid->set($userMemoryId, ['role' => 'user', 'content' => $userMessage]);
$memoryPyramid->set($assistantMemoryId, ['role' => 'assistant', 'content' => $response]);

$turbo->close();

echo json_encode([
    'status' => 'success',
    'response' => $response,
    'model' => $model,
    'context_used' => count($memoryResults),
], JSON_UNESCAPED_UNICODE);
