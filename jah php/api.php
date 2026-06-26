<?php

declare(strict_types=1);

$basePath = __DIR__;

require_once $basePath . '/core/Autoloader.php';

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
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$configFile = $basePath . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration file not found.'], JSON_THROW_ON_ERROR);
    exit;
}

$config = require $configFile;

$engine = JahEngine::getInstance();
$engine->boot($config);

$storagePath = $config['paths']['datacore_storage'] ?? $basePath . '/memory/datacore';
$hotStoragePath = $config['paths']['hot_storage'] ?? $basePath . '/memory/pyramid';

$turbo = new DataCoreTurbo($storagePath, 1000);
$memoryPyramid = new MemoryPyramid($hotStoragePath);

$method = strtoupper($_SERVER['REQUEST_METHOD']);

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'status';

    switch ($action) {
        case 'list':
            $collection = $_GET['collection'] ?? 'memories';
            $limit = min((int) ($_GET['limit'] ?? 50), 500);

            $results = $turbo->query($collection, function (array $doc) {
                return !empty($doc) && ($doc['_deleted'] ?? false) !== true;
            });

            $total = count($results);
            $results = array_slice($results, 0, $limit);

            echo json_encode([
                'status' => 'success',
                'total' => $total,
                'limit' => $limit,
                'data' => $results,
            ], JSON_THROW_ON_ERROR);
            break;

        case 'stats':
            $turboStats = $turbo->getStats();
            $pyramidStats = $memoryPyramid->stats();
            $binaryFiles = glob($storagePath . '/data/*.bin') ?: [];

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'turbo' => $turboStats,
                    'pyramid' => $pyramidStats,
                    'binary_segments' => count($binaryFiles),
                ],
            ], JSON_THROW_ON_ERROR);
            break;

        case 'status':
        default:
            echo json_encode([
                'status' => 'success',
                'service' => 'Jah-Qwen Bridge API',
                'version' => $config['version'],
                'storage_engine' => 'DataCoreTurbo (binary) + MemoryPyramid',
                'timestamp' => time(),
            ], JSON_THROW_ON_ERROR);
            break;
    }

    $turbo->close();
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

    switch ($action) {
        case 'save':
            $collection = $input['collection'] ?? 'memories';
            $data = $input['data'] ?? [];
            $tier = $input['tier'] ?? 'hot';

            if (empty($data)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required field: data'], JSON_THROW_ON_ERROR);
                break;
            }

            $id = $data['id'] ?? bin2hex(random_bytes(8));
            $data['id'] = $id;
            $data['_ts'] = time();
            $data['_tier'] = $tier;

            $turbo->insert($collection, $data);
            $memoryPyramid->set($id, $data);

            $engine->getEventBus()->publish('memory.stored', [
                'collection' => $collection,
                'id' => $id,
                'tier' => $tier,
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => "Memory stored in {$tier}",
                'id' => $id,
                'collection' => $collection,
            ], JSON_THROW_ON_ERROR);
            break;

        case 'retrieve':
            $collection = $input['collection'] ?? 'memories';
            $id = $input['id'] ?? '';

            if (empty($id)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required field: id'], JSON_THROW_ON_ERROR);
                break;
            }

            $result = $turbo->find($collection, $id);

            if ($result === null) {
                http_response_code(404);
                echo json_encode(['status' => 'not_found', 'id' => $id], JSON_THROW_ON_ERROR);
            } else {
                echo json_encode(['status' => 'success', 'data' => $result], JSON_THROW_ON_ERROR);
            }
            break;

        case 'search':
            $collection = $input['collection'] ?? 'memories';
            $query = $input['query'] ?? '';
            $limit = min((int) ($input['limit'] ?? 20), 100);

            if (empty($query)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required field: query'], JSON_THROW_ON_ERROR);
                break;
            }

            $queryLower = strtolower($query);
            $queryTerms = array_filter(explode(' ', $queryLower));

            $results = $turbo->query($collection, function (array $doc) use ($queryTerms, $queryLower) {
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

            $results = array_slice($results, 0, $limit);

            echo json_encode([
                'status' => 'success',
                'query' => $query,
                'count' => count($results),
                'data' => $results,
            ], JSON_THROW_ON_ERROR);
            break;

        case 'delete':
            $collection = $input['collection'] ?? 'memories';
            $id = $input['id'] ?? '';

            if (empty($id)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required field: id'], JSON_THROW_ON_ERROR);
                break;
            }

            $turbo->insert($collection, ['id' => $id, '_deleted' => true, '_ts' => time()]);

            echo json_encode(['status' => 'success', 'id' => $id], JSON_THROW_ON_ERROR);
            break;

        case 'batch':
            $collection = $input['collection'] ?? 'memories';
            $docs = $input['docs'] ?? [];

            if (empty($docs) || !is_array($docs)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required field: docs (array)'], JSON_THROW_ON_ERROR);
                break;
            }

            $count = $turbo->batchInsert($collection, $docs);

            echo json_encode([
                'status' => 'success',
                'inserted' => $count,
                'collection' => $collection,
            ], JSON_THROW_ON_ERROR);
            break;

        case 'pyramid_get':
            $key = $input['key'] ?? '';
            if (empty($key)) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required field: key'], JSON_THROW_ON_ERROR);
                break;
            }
            $result = $memoryPyramid->get($key);
            if ($result === null) {
                http_response_code(404);
                echo json_encode(['status' => 'not_found', 'key' => $key], JSON_THROW_ON_ERROR);
            } else {
                echo json_encode(['status' => 'success', 'data' => $result], JSON_THROW_ON_ERROR);
            }
            break;

        case 'pyramid_set':
            $key = $input['key'] ?? '';
            $value = $input['value'] ?? null;
            $ttl = (int) ($input['ttl'] ?? 0);
            if (empty($key) || $value === null) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields: key, value'], JSON_THROW_ON_ERROR);
                break;
            }
            $memoryPyramid->set($key, $value, $ttl);
            echo json_encode(['status' => 'success', 'key' => $key, 'ttl' => $ttl], JSON_THROW_ON_ERROR);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => "Unknown action: {$action}"], JSON_THROW_ON_ERROR);
            break;
    }

    $turbo->close();
    $engine->shutdown();
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed.'], JSON_THROW_ON_ERROR);
$turbo->close();
$engine->shutdown();
