<?php

declare(strict_types=1);

$boot = require dirname(__DIR__) . '/app/bootstrap.php';

use Jah\DataCore\DataCoreTurbo;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$config = $boot['config'];
$input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
$action = (string) ($input['action'] ?? 'status');
$collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($input['collection'] ?? 'memories'));

$storagePath = (string) $config['paths']['datacore_storage'];
$turbo = new DataCoreTurbo($storagePath, 500);

switch ($action) {
    case 'status':
        echo json_encode([
            'status' => 'success',
            'service' => 'JAH-Qwen Bridge',
            'version' => $config['version'],
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE);
        break;

    case 'save':
        $data = is_array($input['data'] ?? null) ? $input['data'] : [];
        $tier = (string) ($input['tier'] ?? 'hot');
        $id = (string) ($data['id'] ?? bin2hex(random_bytes(8)));
        $data['id'] = $id;
        $data['_ts'] = time();
        $data['_tier'] = $tier;
        $turbo->insert($collection, $data);
        echo json_encode(['status' => 'success', 'id' => $id, 'collection' => $collection], JSON_UNESCAPED_UNICODE);
        break;

    case 'search':
        $query = mb_strtolower((string) ($input['query'] ?? ''));
        $limit = min((int) ($input['limit'] ?? 20), 100);
        $terms = array_values(array_filter(preg_split('/\s+/', $query) ?: []));
        $results = $turbo->query($collection, static function (array $doc) use ($terms, $query): bool {
            if (($doc['_deleted'] ?? false) === true) return false;
            $searchable = mb_strtolower(json_encode($doc, JSON_UNESCAPED_UNICODE) ?: '');
            if ($query !== '' && str_contains($searchable, $query)) return true;
            foreach ($terms as $term) {
                if (mb_strlen($term) >= 3 && str_contains($searchable, $term)) return true;
            }
            return false;
        });
        echo json_encode(['status' => 'success', 'count' => count($results), 'data' => array_slice($results, 0, $limit)], JSON_UNESCAPED_UNICODE);
        break;

    case 'retrieve':
        $id = (string) ($input['id'] ?? '');
        $result = $turbo->find($collection, $id);
        if ($result === null || ($result['_deleted'] ?? false) === true) {
            echo json_encode(['status' => 'not_found', 'id' => $id], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['status' => 'success', 'data' => $result], JSON_UNESCAPED_UNICODE);
        }
        break;

    case 'delete':
        $id = (string) ($input['id'] ?? '');
        $turbo->insert($collection, ['id' => $id, '_deleted' => true, '_ts' => time()]);
        echo json_encode(['status' => 'success', 'id' => $id], JSON_UNESCAPED_UNICODE);
        break;

    case 'batch':
        $docs = is_array($input['docs'] ?? null) ? $input['docs'] : [];
        $count = $turbo->batchInsert($collection, $docs);
        echo json_encode(['status' => 'success', 'inserted' => $count], JSON_UNESCAPED_UNICODE);
        break;

    case 'stats':
        echo json_encode(['status' => 'success', 'data' => $turbo->getStats()], JSON_UNESCAPED_UNICODE);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => "Unknown action: {$action}"], JSON_UNESCAPED_UNICODE);
}

$turbo->close();
