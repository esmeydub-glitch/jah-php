<?php

declare(strict_types=1);

$boot = require dirname(__DIR__) . '/app/bootstrap.php';
$config = $boot['config'];

use Jah\Memory\TieredMemory;
use Jah\Http\JsonTransport;

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$storagePath = (string)$config['paths']['datacore_storage'];
$hotStoragePath = (string)$config['paths']['hot_storage'];
$tiered = new TieredMemory($storagePath, $hotStoragePath);
require_once dirname(__DIR__) . '/app/actions/MemoryActionScript.php';
$runtime = new MemoryActionScript($tiered, $config);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$input = JsonTransport::decodeRequest((int)($config['security']['max_payload_bytes'] ?? 1048576));

$action = (string)($input['action'] ?? ($method === 'GET' ? 'status' : 'chat'));
$collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($input['collection'] ?? 'memories')) ?: 'memories';

try {
    switch ($action) {
        case 'status':
            $salkStatus = $runtime->runSalkPreflight('api.status');
            $output = [
                'status' => 'success',
                'service' => 'JAH MemoryAgent',
                'runtime' => 'PHP puro + ActionScript PHP',
                'qwen_cloud' => 'DashScope compatible API via native PHP cURL',
                'memory' => 'DataCoreTurbo + MemoryPyramid Hot/Warm/Cold',
                'salk' => $salkStatus['result'] ?? [],
            ];
            break;

        case 'salk_status':
            $salkStatus = $runtime->runSalkPreflight('api.salk_status');
            $output = ['status' => 'success', 'salk' => $salkStatus['result'] ?? []];
            break;

        case 'salk_package_vectors':
            $vectors = $runtime->runSalkPackageVectorScan();
            $output = ['status' => 'success', 'package_vectors' => $vectors['result'] ?? []];
            break;

        case 'stats':
            $stats = $runtime->stats();
            $output = ['status' => 'success', 'data' => $stats['result'] ?? []];
            break;

        case 'save':
            $data = $input['data'] ?? $input['content'] ?? [];
            if (is_string($data)) $data = ['content' => $data];
            if (!is_array($data)) $data = [];
            $id = (string)($data['id'] ?? $input['id'] ?? bin2hex(random_bytes(8)));
            $tier = (string)($input['tier'] ?? $data['_tier'] ?? 'hot');
            $saved = $runtime->save($id, $data, $tier);
            $output = ['status' => 'success', 'data' => $saved['result'] ?? ['id' => $id, 'tier' => $tier]];
            break;

        case 'retrieve':
        case 'get':
            $id = trim((string)($input['id'] ?? ''));
            if ($id === '') throw new RuntimeException('id required');
            $found = $runtime->retrieve($id);
            $result = $found['result'] ?? [];
            $output = ($result['found'] ?? false)
                ? ['status' => 'success', 'data' => $result['memory']]
                : ['status' => 'not_found', 'id' => $id];
            break;

        case 'search':
            $query = trim((string)($input['query'] ?? ''));
            if ($query === '') throw new RuntimeException('query required');
            $limit = max(1, min((int)($input['limit'] ?? 20), 100));
            $found = $runtime->search($query, $collection, $limit);
            $output = ['status' => 'success', 'data' => $found['result']['memories'] ?? [], 'total' => $found['result']['count'] ?? 0];
            break;

        case 'delete':
        case 'forget':
            $id = trim((string)($input['id'] ?? ''));
            if ($id === '') throw new RuntimeException('id required');
            $deleted = $runtime->delete($id, $collection);
            $output = ['status' => 'success', 'data' => $deleted['result'] ?? ['id' => $id, 'forgotten' => true]];
            break;

        case 'batch':
            $docs = $input['docs'] ?? [];
            if (!is_array($docs)) throw new RuntimeException('docs array required');
            $saved = 0;
            foreach ($docs as $doc) {
                if (!is_array($doc)) continue;
                $id = (string)($doc['id'] ?? bin2hex(random_bytes(8)));
                $tier = (string)($doc['_tier'] ?? $input['tier'] ?? 'hot');
                $runtime->save($id, $doc, $tier);
                $saved++;
            }
            $output = ['status' => 'success', 'inserted' => $saved];
            break;

        case 'migrate':
            $migrated = $runtime->migrate();
            $output = ['status' => 'success', 'data' => $migrated['result'] ?? []];
            break;

        case 'chat':
        default:
            $message = trim((string)($input['message'] ?? ''));
            if ($message === '') throw new RuntimeException('message required');
            $model = (string)($input['model'] ?? $config['qwen']['model'] ?? 'qwen-max');
            $agent = $runtime->runAgent($message, $collection, $model);
            $output = array_merge(['status' => 'success', 'model' => $model], $agent);
            break;
    }
} catch (Throwable $e) {
    http_response_code(400);
    $output = ['status' => 'error', 'error' => $e->getMessage()];
}

$tiered->close();
JsonTransport::respond($output, $runtime->getSalkGuard(), 'api.response');
