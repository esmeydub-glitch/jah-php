<?php

declare(strict_types=1);

use Jah\Actions\ActionScript;
use Jah\Memory\TieredMemory;

require_once dirname(__DIR__, 2) . '/php_actionscript_php_doc/ActionScriptEngine.php';

/**
 * MemoryActionScript
 * Runtime ActionScript PHP for the official MemoryAgent flow.
 * All actions are pure PHP and orchestrate DataCoreTurbo + MemoryPyramid + Qwen cURL.
 */
final class MemoryActionScript
{
    private TieredMemory $memory;
    private array $config;
    private array $lastTrace = [];

    public function __construct(TieredMemory $memory, array $config)
    {
        $this->memory = $memory;
        $this->config = $config;
        $this->registerActions();
    }

    public function runAgent(string $message, string $collection = 'memories', string $model = 'qwen-max'): array
    {
        $trace = [];

        $retrieved = $this->run('memory.search_context', [
            'query' => $message,
            'collection' => $collection,
            'limit' => 10,
        ]);
        $trace[] = $this->traceFromResult($retrieved);

        $context = $this->run('memory.build_context', [
            'message' => $message,
            'memories' => $retrieved['result']['memories'] ?? [],
        ]);
        $trace[] = $this->traceFromResult($context);

        $answer = $this->run('qwen.ask', [
            'message' => $message,
            'context' => $context['result']['context'] ?? '',
            'model' => $model,
        ]);
        $trace[] = $this->traceFromResult($answer);

        $store = $this->run('memory.store_interaction', [
            'message' => $message,
            'response' => $answer['result']['response'] ?? '',
            'collection' => $collection,
        ]);
        $trace[] = $this->traceFromResult($store);

        $this->lastTrace = $trace;

        return [
            'response' => $answer['result']['response'] ?? ($answer['error'] ?? 'Sin respuesta.'),
            'context_used' => count($retrieved['result']['memories'] ?? []),
            'context_preview' => $context['result']['context'] ?? '',
            'memories' => $retrieved['result']['memories'] ?? [],
            'stored' => $store['result'] ?? [],
            'actions_trace' => $trace,
        ];
    }

    public function save(string $id, array $content, string $tier = 'hot'): array
    {
        return $this->run('memory.save', ['id' => $id, 'content' => $content, 'tier' => $tier]);
    }

    public function search(string $query, string $collection = 'memories', int $limit = 20): array
    {
        return $this->run('memory.search_context', ['query' => $query, 'collection' => $collection, 'limit' => $limit]);
    }

    public function retrieve(string $id): array
    {
        return $this->run('memory.retrieve', ['id' => $id]);
    }

    public function delete(string $id, string $collection = 'memories'): array
    {
        return $this->run('memory.forget', ['id' => $id, 'collection' => $collection]);
    }

    public function stats(): array
    {
        return $this->run('memory.stats', []);
    }

    public function migrate(): array
    {
        return $this->run('memory.migrate', []);
    }

    public function getLastTrace(): array
    {
        return $this->lastTrace;
    }

    private function registerActions(): void
    {
        ActionScript::define('memory.search_context')
            ->requires(['query'])
            ->timeout(3000)
            ->handler(function (array $data): array {
                $query = (string)$data['query'];
                $collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($data['collection'] ?? 'memories')) ?: 'memories';
                $limit = max(1, min((int)($data['limit'] ?? 10), 50));
                $memories = $this->memory->search($query, $collection, $limit);
                return ['memories' => $memories, 'count' => count($memories)];
            });

        ActionScript::define('memory.build_context')
            ->requires(['message', 'memories'])
            ->timeout(1000)
            ->handler(function (array $data): array {
                $date = new DateTimeImmutable('now', new DateTimeZone((string)($this->config['timezone'] ?? 'America/Mexico_City')));
                $lines = ['Fecha actual: ' . $date->format('Y-m-d H:i:s T')];
                $memories = is_array($data['memories']) ? $data['memories'] : [];

                if ($memories === []) {
                    $lines[] = 'Memorias recuperadas: ninguna.';
                } else {
                    $lines[] = 'Memorias recuperadas:';
                    foreach (array_slice($memories, 0, 10) as $item) {
                        if (!is_array($item)) continue;
                        $content = $item['content'] ?? json_encode($item, JSON_UNESCAPED_UNICODE);
                        if (is_array($content)) $content = json_encode($content, JSON_UNESCAPED_UNICODE);
                        $role = (string)($item['role'] ?? 'memory');
                        $tier = (string)($item['_memory_tier'] ?? $item['_tier'] ?? 'hot');
                        $lines[] = '- [' . $role . '|' . $tier . '] ' . substr((string)$content, 0, 280);
                    }
                }

                return ['context' => implode("\n", $lines)];
            });

        ActionScript::define('qwen.ask')
            ->requires(['message', 'context'])
            ->timeout(60000)
            ->handler(function (array $data): array {
                $apiKey = (string)($_ENV['QWEN_API_KEY'] ?? getenv('QWEN_API_KEY') ?: '');
                $model = (string)($data['model'] ?? $this->config['qwen']['model'] ?? getenv('QWEN_MODEL') ?: 'qwen-max');
                $baseUrl = (string)($this->config['qwen']['base_url'] ?? getenv('QWEN_BASE_URL') ?: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1');
                require_once dirname(__DIR__) . '/QwenConnector.php';
                $qwen = new QwenConnector($apiKey, $baseUrl);
                return ['response' => $qwen->chat((string)$data['message'], (string)$data['context'], $model), 'model' => $model];
            });

        ActionScript::define('memory.store_interaction')
            ->requires(['message', 'response'])
            ->timeout(3000)
            ->handler(function (array $data): array {
                $now = time();
                $uid = 'user_' . bin2hex(random_bytes(6));
                $aid = 'assistant_' . bin2hex(random_bytes(6));
                $this->memory->store($uid, ['role' => 'user', 'content' => (string)$data['message'], 'tags' => ['conversation'], '_ts' => $now], 'hot');
                $this->memory->store($aid, ['role' => 'assistant', 'content' => (string)$data['response'], 'tags' => ['conversation'], '_ts' => $now], 'hot');
                return ['user_id' => $uid, 'assistant_id' => $aid, 'tier' => 'hot'];
            });

        ActionScript::define('memory.save')
            ->requires(['id', 'content'])
            ->timeout(3000)
            ->handler(function (array $data): array {
                $tier = (string)($data['tier'] ?? 'hot');
                $content = is_array($data['content']) ? $data['content'] : ['content' => (string)$data['content']];
                $this->memory->store((string)$data['id'], $content, $tier);
                return ['id' => (string)$data['id'], 'tier' => $tier];
            });

        ActionScript::define('memory.retrieve')
            ->requires(['id'])
            ->timeout(3000)
            ->handler(function (array $data): array {
                $memory = $this->memory->retrieve((string)$data['id']);
                return ['id' => (string)$data['id'], 'memory' => $memory, 'found' => $memory !== null];
            });

        ActionScript::define('memory.forget')
            ->requires(['id'])
            ->timeout(3000)
            ->handler(function (array $data): array {
                $collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($data['collection'] ?? 'memories')) ?: 'memories';
                $this->memory->forget((string)$data['id'], $collection);
                return ['id' => (string)$data['id'], 'forgotten' => true];
            });

        ActionScript::define('memory.migrate')
            ->timeout(10000)
            ->handler(fn(array $data): array => $this->memory->migrate('memories'));

        ActionScript::define('memory.stats')
            ->timeout(3000)
            ->handler(fn(array $data): array => $this->memory->stats());
    }

    private function run(string $action, array $data): array
    {
        return ActionScript::run($action, $data);
    }

    private function traceFromResult(array $result): array
    {
        return [
            'action' => $result['action'] ?? 'unknown',
            'success' => (bool)($result['success'] ?? false),
            'duration_ms' => isset($result['duration_ms']) ? round((float)$result['duration_ms'], 3) : null,
            'error' => $result['error'] ?? null,
        ];
    }
}
