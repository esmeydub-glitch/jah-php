<?php

declare(strict_types=1);

/**
 * Jah-Qwen Bridge API — REST endpoint for external AI agents.
 *
 * Endpoints:
 *   POST /bridge.php              → store/search/delete memory
 *   GET  /bridge.php?action=list  → list memories
 *   GET  /bridge.php?action=stats → tier statistics
 *   POST /bridge.php?action=migrate → trigger tier migration
 */

require_once __DIR__ . '/core/Autoloader.php';

Autoloader::register();
Autoloader::addNamespace('Jah\\Core\\',    __DIR__ . '/core');
Autoloader::addNamespace('Jah\\Agents\\',  __DIR__ . '/agents');
Autoloader::addNamespace('Jah\\Memory\\',  __DIR__ . '/memory');
Autoloader::addNamespace('Jah\\Network\\', __DIR__ . '/network');
Autoloader::addNamespace('Jah\\Cache\\',   __DIR__ . '/cache');

use Jah\Core\JahEngine;
use Jah\Memory\TieredMemory;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$configFile = __DIR__ . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration file not found.'], JSON_THROW_ON_ERROR);
    exit;
}

$config = require $configFile;

$engine = JahEngine::getInstance();
$engine->boot($config);

$memoryBasePath = $config['paths']['tiered_memory'] ?? __DIR__ . '/memory/tiers';
$tierConfig = $config['tiered_memory_config'] ?? [];

$tieredMemory = new TieredMemory($memoryBasePath, $tierConfig);

$method = strtoupper($_SERVER['REQUEST_METHOD']);

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'status';

    switch ($action) {
        case 'list':
            $tier = $_GET['tier'] ?? '';
            $limit = min((int) ($_GET['limit'] ?? 50), 500);
            $offset = max((int) ($_GET['offset'] ?? 0), 0);

            $result = $tieredMemory->listAll($tier, $limit, $offset);
            echo json_encode(['status' => 'success', 'data' => $result], JSON_THROW_ON_ERROR);
            break;

        case 'stats':
            $stats = $tieredMemory->getStats();
            echo json_encode(['status' => 'success', 'data' => $stats], JSON_THROW_ON_ERROR);
            break;

        case 'status':
        default:
            echo json_encode([
                'status' => 'success',
                'service' => 'Jah-Qwen Bridge API',
                'version' => $config['version'],
                'timestamp' => time(),
            ], JSON_THROW_ON_ERROR);
            break;
    }

    $engine->shutdown();
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload.'], JSON_THROW_ON_ERROR);
        $engine->shutdown();
        exit;
    }

    $action = $input['action'] ?? '';
    $tier = $input['tier'] ?? 'hot';
    $key = $input['key'] ?? '';

    switch ($action) {
        case 'save':
            if (empty($key)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required field: key'], JSON_THROW_ON_ERROR);
                break;
            }

            $data = $input['data'] ?? [];
            $tags = $input['tags'] ?? [];
            if (!empty($tags)) {
                $data['tags'] = $tags;
            }

            $tieredMemory->store($tier, $key, $data);

            $engine->getEventBus()->publish('memory.stored', [
                'tier' => $tier,
                'key' => $key,
                'tags' => $tags,
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => "Memory stored in {$tier}",
                'key' => $key,
            ], JSON_THROW_ON_ERROR);
            break;

        case 'retrieve':
            if (empty($key)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required field: key'], JSON_THROW_ON_ERROR);
                break;
            }

            $result = $tieredMemory->retrieve($tier, $key);

            if ($result === null) {
                http_response_code(404);
                echo json_encode(['status' => 'not_found', 'key' => $key, 'tier' => $tier], JSON_THROW_ON_ERROR);
            } else {
                echo json_encode(['status' => 'success', 'data' => $result], JSON_THROW_ON_ERROR);
            }
            break;

        case 'search':
            $query = $input['query'] ?? '';
            $tiers = $input['tiers'] ?? ['hot', 'warm', 'cold'];
            $limit = min((int) ($input['limit'] ?? 20), 100);

            if (empty($query)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required field: query'], JSON_THROW_ON_ERROR);
                break;
            }

            $results = $tieredMemory->search($query, $tiers, $limit);

            echo json_encode([
                'status' => 'success',
                'query' => $query,
                'count' => count($results),
                'data' => $results,
            ], JSON_THROW_ON_ERROR);
            break;

        case 'tags':
            $tags = $input['tags'] ?? [];
            $tiers = $input['tiers'] ?? ['hot', 'warm', 'cold'];
            $limit = min((int) ($input['limit'] ?? 20), 100);

            if (empty($tags)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required field: tags'], JSON_THROW_ON_ERROR);
                break;
            }

            $results = $tieredMemory->getByTags($tags, $tiers, $limit);

            echo json_encode([
                'status' => 'success',
                'tags' => $tags,
                'count' => count($results),
                'data' => $results,
            ], JSON_THROW_ON_ERROR);
            break;

        case 'delete':
            if (empty($key)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required field: key'], JSON_THROW_ON_ERROR);
                break;
            }

            $deleted = $tieredMemory->delete($key);

            echo json_encode([
                'status' => $deleted ? 'success' : 'not_found',
                'key' => $key,
            ], JSON_THROW_ON_ERROR);
            break;

        case 'move':
            if (empty($key) || empty($input['to_tier'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields: key, to_tier'], JSON_THROW_ON_ERROR);
                break;
            }

            $moved = $tieredMemory->moveTier($key, $input['to_tier']);

            echo json_encode([
                'status' => $moved ? 'success' : 'error',
                'key' => $key,
                'to_tier' => $input['to_tier'],
            ], JSON_THROW_ON_ERROR);
            break;

        case 'migrate':
            $migrated = $tieredMemory->migrateTiers();

            echo json_encode([
                'status' => 'success',
                'migrated_count' => count($migrated),
                'migrated' => $migrated,
            ], JSON_THROW_ON_ERROR);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => "Unknown action: {$action}"], JSON_THROW_ON_ERROR);
            break;
    }

    $engine->shutdown();
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed.'], JSON_THROW_ON_ERROR);
$engine->shutdown();
