<?php

declare(strict_types=1);

use Jah\Actions\ActionScript;
use Jah\Memory\TieredMemory;
use Jah\Security\SalkGuard;

require_once dirname(__DIR__, 2) . '/php_actionscript_php_doc/ActionScriptEngine.php';
require_once __DIR__ . '/SalkSecurityActionScript.php';

/**
 * MemoryActionScript
 * Runtime ActionScript PHP for the official MemoryAgent flow.
 * All actions are pure PHP and orchestrate DataCoreTurbo + MemoryPyramid + Qwen connector.
 */
final class MemoryActionScript
{
    private TieredMemory $memory;
    private SalkGuard $salk;
    private array $config;
    private array $lastTrace = [];

    public function __construct(TieredMemory $memory, array $config)
    {
        $this->memory = $memory;
        $this->config = $config;
        $this->salk = new SalkGuard(dirname(__DIR__, 2), $config);
        new SalkSecurityActionScript($this->salk);
        $this->registerActions();
    }

    public function runAgent(string $message, string $collection = 'memories', string $model = 'qwen-max'): array
    {
        $trace = [];

        $salkPreflight = $this->run('salk.preflight', [
            'context' => 'memoryagent.run',
        ]);
        $trace[] = $this->traceFromResult($salkPreflight);
        $preflightOk = (bool)($salkPreflight['success'] ?? false)
            && (bool)($salkPreflight['result']['ok'] ?? false);
        if (!$preflightOk) {
            $audit = $this->run('salk.audit_event', [
                'event' => 'memoryagent.blocked',
                'result' => ['status' => false, 'ok' => false],
                'metadata' => ['reason' => 'salk_preflight_failed'],
            ]);
            $trace[] = $this->traceFromResult($audit);
            $this->lastTrace = $this->salk->maskSecrets($trace);
            return $this->salk->maskSecrets([
                'response' => 'SALK bloqueó la ejecución porque el preflight de seguridad falló.',
                'blocked_by_salk' => true,
                'context_used' => 0,
                'context_preview' => '',
                'memories' => [],
                'classification' => [],
                'stored' => ['stored' => false, 'reason' => 'salk_preflight_failed'],
                'actions_trace' => $trace,
                'salk' => ['preflight' => $salkPreflight['result'] ?? [], 'audit' => $audit['result'] ?? []],
            ]);
        }

        $classification = $this->run('memory.classify_input', [
            'message' => $message,
        ]);
        $trace[] = $this->traceFromResult($classification);

        $retrieved = $this->run('memory.search_context', [
            'query' => $message,
            'collection' => $collection,
            'limit' => 10,
        ]);
        $trace[] = $this->traceFromResult($retrieved);

        $context = $this->run('memory.build_context', [
            'message' => $message,
            'memories' => $retrieved['result']['memories'] ?? [],
            'classification' => $classification['result'] ?? [],
        ]);
        $trace[] = $this->traceFromResult($context);

        $answer = $this->run('qwen.ask', [
            'message' => $message,
            'context' => $context['result']['context'] ?? '',
            'model' => $model,
        ]);
        $trace[] = $this->traceFromResult($answer);

        $answerBlocked = (bool)($answer['result']['blocked_by_salk'] ?? false);
        if (($answer['success'] ?? false) !== true || $answerBlocked) {
            $audit = $this->run('salk.audit_event', [
                'event' => $answerBlocked ? 'memoryagent.blocked' : 'memoryagent.qwen_failed',
                'result' => ['status' => false, 'ok' => false],
                'metadata' => ['collection' => $collection],
            ]);
            $trace[] = $this->traceFromResult($audit);
            $this->lastTrace = $this->salk->maskSecrets($trace);
            return $this->salk->maskSecrets([
                'response' => $answerBlocked
                    ? (string)($answer['result']['response'] ?? 'SALK bloqueó la solicitud.')
                    : ($answer['error'] ?? 'Qwen no pudo completar la solicitud.'),
                'blocked_by_salk' => $answerBlocked,
                'qwen_failed' => !$answerBlocked,
                'context_used' => count($retrieved['result']['memories'] ?? []),
                'context_preview' => $context['result']['context'] ?? '',
                'memories' => $retrieved['result']['memories'] ?? [],
                'memory_search' => $retrieved['result']['metrics'] ?? [],
                'classification' => $classification['result'] ?? [],
                'stored' => ['stored' => false, 'reason' => $answerBlocked ? 'secret_detected_not_sent' : 'qwen_failed'],
                'actions_trace' => $trace,
                'salk' => ['preflight' => $salkPreflight['result'] ?? [], 'audit' => $audit['result'] ?? []],
            ]);
        }

        $store = $this->run('memory.store_interaction', [
            'message' => $message,
            'response' => $answer['result']['response'] ?? '',
            'collection' => $collection,
            'classification' => $classification['result'] ?? [],
        ]);
        $trace[] = $this->traceFromResult($store);

        $audit = $this->run('salk.audit_event', [
            'event' => 'memoryagent.run',
            'result' => ['status' => 'success'],
            'metadata' => [
                'collection' => $collection,
                'classification' => $classification['result']['type'] ?? 'unknown',
                'stored' => $store['result']['stored'] ?? false,
                'context_used' => count($retrieved['result']['memories'] ?? []),
            ],
        ]);
        $trace[] = $this->traceFromResult($audit);

        $this->lastTrace = $this->salk->maskSecrets($trace);

        return $this->salk->maskSecrets([
            'response' => $answer['result']['response'] ?? ($answer['error'] ?? 'Sin respuesta.'),
            'context_used' => count($retrieved['result']['memories'] ?? []),
            'context_preview' => $context['result']['context'] ?? '',
            'memories' => $retrieved['result']['memories'] ?? [],
            'memory_search' => $retrieved['result']['metrics'] ?? [],
            'classification' => $classification['result'] ?? [],
            'stored' => $store['result'] ?? [],
            'actions_trace' => $trace,
            'salk' => [
                'preflight' => $salkPreflight['result'] ?? [],
                'audit' => $audit['result'] ?? [],
            ],
        ]);
    }

    public function save(string $id, array $content, string $tier = 'hot', string $collection = 'memories'): array
    {
        return $this->run('memory.save', ['id' => $id, 'content' => $content, 'tier' => $tier, 'collection' => $collection]);
    }

    public function search(string $query, string $collection = 'memories', int $limit = 20): array
    {
        return $this->run('memory.search_context', ['query' => $query, 'collection' => $collection, 'limit' => $limit]);
    }

    public function retrieve(string $id, string $collection = 'memories'): array
    {
        return $this->run('memory.retrieve', ['id' => $id, 'collection' => $collection]);
    }

    public function delete(string $id, string $collection = 'memories'): array
    {
        return $this->run('memory.forget', ['id' => $id, 'collection' => $collection]);
    }

    public function stats(string $collection = 'memories'): array
    {
        return $this->run('memory.stats', ['collection' => $collection]);
    }

    public function migrate(string $collection = 'memories'): array
    {
        return $this->run('memory.migrate', ['collection' => $collection]);
    }

    public function reindex(string $collection = 'memories'): array
    {
        return $this->run('memory.reindex', ['collection' => $collection]);
    }

    public function getLastTrace(): array
    {
        return $this->lastTrace;
    }

    public function getSalkGuard(): SalkGuard
    {
        return $this->salk;
    }

    public function runSalkPreflight(string $context = 'runtime'): array
    {
        return $this->run('salk.preflight', ['context' => $context]);
    }

    public function runSalkPackageVectorScan(): array
    {
        return $this->run('salk.scan_package_vectors', []);
    }

    public function validatePublicPayload(array $payload, string $context = 'payload.public'): array
    {
        return $this->run('salk.validate_public_payload', [
            'payload' => $payload,
            'context' => $context,
        ]);
    }

    private function registerActions(): void
    {
        ActionScript::define('memory.classify_input')
            ->requires(['message'])
            ->timeout(1000)
            ->handler(function (array $data): array {
                return $this->classifyInput((string)$data['message']);
            });

        ActionScript::define('memory.search_context')
            ->requires(['query'])
            ->timeout(3000)
            ->handler(function (array $data): array {
                $query = (string)$data['query'];
                $collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($data['collection'] ?? 'memories')) ?: 'memories';
                $limit = max(1, min((int)($data['limit'] ?? 10), 50));
                $memories = $this->memory->search($query, $collection, $limit);
                $memories = is_array($memories) ? $this->salk->maskSecrets($memories) : [];
                return [
                    'memories' => $memories,
                    'count' => count($memories),
                    'metrics' => $this->memory->getLastSearchMetrics(),
                ];
            });

        ActionScript::define('memory.build_context')
            ->requires(['message', 'memories'])
            ->timeout(1000)
            ->handler(function (array $data): array {
                $date = new DateTimeImmutable('now', new DateTimeZone((string)($this->config['timezone'] ?? 'America/Mexico_City')));
                $lines = ['Fecha actual: ' . $date->format('Y-m-d H:i:s T')];

                $classification = is_array($data['classification'] ?? null) ? $data['classification'] : [];
                if ($classification !== []) {
                    $lines[] = 'Decision de memoria: ' . (($classification['store'] ?? false) ? 'guardar' : 'no guardar')
                        . ' | tipo=' . (string)($classification['type'] ?? 'unknown')
                        . ' | razon=' . (string)($classification['reason'] ?? 'n/a');
                }

                $memories = is_array($data['memories']) ? $data['memories'] : [];

                if ($memories === []) {
                    $lines[] = 'Memorias recuperadas: ninguna.';
                } else {
                    $lines[] = 'Memorias recuperadas:';
                    foreach (array_slice($memories, 0, 10) as $item) {
                        if (!is_array($item)) continue;
                        $content = $item['content'] ?? var_export($item, true);
                        if (is_array($content)) $content = var_export($content, true);
                        $content = $this->salk->maskText((string)$content);
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
                if (strlen($model) > 100 || preg_match('/^[a-zA-Z0-9_.-]+$/', $model) !== 1) {
                    throw new InvalidArgumentException('Modelo Qwen inválido');
                }
                $baseUrl = (string)($this->config['qwen']['base_url'] ?? getenv('QWEN_BASE_URL') ?: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1');
                $message = (string)$data['message'];
                $context = $this->salk->maskText((string)$data['context']);
                if ($this->salk->containsSecret($message)) {
                    return ['response' => 'SALK bloqueó el envío: el mensaje contiene un secreto o API key.', 'model' => $model, 'blocked_by_salk' => true];
                }
                require_once dirname(__DIR__) . '/QwenConnector.php';
                $qwen = new QwenConnector($apiKey, $baseUrl);
                return ['response' => $qwen->chat($message, $context, $model), 'model' => $model];
            });

        ActionScript::define('memory.store_interaction')
            ->requires(['message', 'response'])
            ->timeout(3000)
            ->handler(function (array $data): array {
                $message = (string)$data['message'];
                $response = (string)$data['response'];
                $classification = is_array($data['classification'] ?? null)
                    ? $data['classification']
                    : $this->classifyInput($message);

                if ($this->salk->containsSecret($message)) {
                    $this->salk->auditEvent('salk.secret_memory_blocked', ['ok' => true], ['type' => 'memory.store_interaction']);
                    return [
                        'stored' => false,
                        'reason' => 'secret_detected_not_stored',
                        'type' => 'secret_blocked',
                        'tier' => null,
                    ];
                }

                if (($classification['store'] ?? false) !== true) {
                    return [
                        'stored' => false,
                        'reason' => (string)($classification['reason'] ?? 'noise_or_non_memory_input'),
                        'type' => (string)($classification['type'] ?? 'noise'),
                        'tier' => null,
                    ];
                }

                $storeResponse = (bool)($classification['store_response'] ?? false);
                if ($storeResponse && trim($response) === '') {
                    return [
                        'stored' => false,
                        'reason' => 'generated_knowledge_response_empty',
                        'type' => (string)($classification['type'] ?? 'knowledge'),
                        'tier' => null,
                    ];
                }
                if ($storeResponse && $this->salk->containsSecret($response)) {
                    $this->salk->auditEvent('salk.secret_response_memory_blocked', ['ok' => true], ['type' => 'memory.store_interaction']);
                    return [
                        'stored' => false,
                        'reason' => 'secret_detected_in_response_not_stored',
                        'type' => 'secret_blocked',
                        'tier' => null,
                    ];
                }

                $now = time();
                $uid = $storeResponse
                    ? 'knowledge_' . substr(hash('sha256', $this->normalizeText($message)), 0, 20)
                    : 'memory_' . bin2hex(random_bytes(6));
                $collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($data['collection'] ?? 'memories')) ?: 'memories';
                $memoryContent = $storeResponse ? $response : $message;
                $this->memory->store($uid, [
                    'role' => $storeResponse ? 'memory' : 'user',
                    'content' => $this->salk->maskText($memoryContent),
                    'source_query' => $storeResponse ? $this->salk->maskText($message) : null,
                    'tags' => array_values(array_filter([
                        'memory',
                        (string)$classification['type'],
                        'classified',
                        $storeResponse ? 'qwen_generated_knowledge' : null,
                    ])),
                    'importance' => (int)($classification['importance'] ?? 5),
                    'classification_reason' => (string)($classification['reason'] ?? 'stored'),
                    '_ts' => $now,
                ], 'hot', $collection);

                return [
                    'stored' => true,
                    'memory_id' => $uid,
                    'user_id' => $storeResponse ? null : $uid,
                    'assistant_id' => $storeResponse ? $uid : null,
                    'assistant_stored' => $storeResponse,
                    'stored_source' => $storeResponse ? 'qwen_response' : 'user_input',
                    'type' => (string)$classification['type'],
                    'importance' => (int)($classification['importance'] ?? 5),
                    'tier' => 'hot',
                ];
            });

        ActionScript::define('memory.save')
            ->requires(['id', 'content'])
            ->timeout(3000)
            ->handler(function (array $data): array {
                $requestedTier = (string)($data['tier'] ?? 'hot');
                $tier = in_array($requestedTier, ['hot', 'warm', 'cold'], true) ? $requestedTier : 'hot';
                $collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($data['collection'] ?? 'memories')) ?: 'memories';
                $content = is_array($data['content']) ? $data['content'] : ['content' => (string)$data['content']];
                $serialized = var_export($content, true);
                if ($this->salk->containsSecret($serialized) || $this->salk->containsSensitiveData($content)) {
                    $this->salk->auditEvent('salk.secret_save_blocked', ['ok' => true], ['id' => (string)$data['id']]);
                    return ['id' => (string)$data['id'], 'tier' => $tier, 'saved' => false, 'reason' => 'secret_detected_not_stored'];
                }
                $content = $this->salk->maskSecrets($content);
                $this->memory->store((string)$data['id'], $content, $tier, $collection);
                return ['id' => (string)$data['id'], 'tier' => $tier, 'collection' => $collection, 'saved' => true];
            });

        ActionScript::define('memory.retrieve')
            ->requires(['id'])
            ->timeout(3000)
            ->handler(function (array $data): array {
                $collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($data['collection'] ?? 'memories')) ?: 'memories';
                $memory = $this->memory->retrieve((string)$data['id'], null, $collection);
                $memory = $memory !== null ? $this->salk->maskSecrets($memory) : null;
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
            ->handler(function (array $data): array {
                $collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($data['collection'] ?? 'memories')) ?: 'memories';
                return $this->memory->migrate($collection);
            });

        ActionScript::define('memory.stats')
            ->timeout(3000)
            ->handler(function (array $data): array {
                $collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($data['collection'] ?? 'memories')) ?: 'memories';
                return $this->memory->stats($collection);
            });

        ActionScript::define('memory.reindex')
            ->timeout(30000)
            ->handler(function (array $data): array {
                $collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($data['collection'] ?? 'memories')) ?: 'memories';
                return $this->memory->rebuildIndexes($collection);
            });
    }

    private function run(string $action, array $data): array
    {
        return ActionScript::run($action, $data);
    }

    private function traceFromResult(array $result): array
    {
        $action = (string)($result['action'] ?? 'unknown');
        $decision = in_array($action, ['memory.classify_input', 'salk.preflight', 'salk.protect_api_key'], true)
            ? ($result['result'] ?? null)
            : null;

        return $this->salk->maskSecrets([
            'action' => $action,
            'success' => (bool)($result['success'] ?? false),
            'duration_ms' => isset($result['duration_ms']) ? round((float)$result['duration_ms'], 3) : null,
            'budget_exceeded' => (bool)($result['budget_exceeded'] ?? false),
            'warning' => $result['warning'] ?? null,
            'error' => $result['error'] ?? null,
            'decision' => $decision,
        ]);
    }

    private function classifyInput(string $message): array
    {
        $original = trim($message);
        $normalized = $this->normalizeText($original);

        if ($normalized === '') {
            return [
                'store' => false,
                'type' => 'empty',
                'importance' => 0,
                'reason' => 'empty_input',
            ];
        }

        $noise = [
            'hola', 'ola', 'hey', 'ok', 'okay', 'gracias', 'thanks', 'jaja', 'jeje',
            'buenos dias', 'buenas tardes', 'buenas noches', 'que eres', 'quien eres',
            'como estas', 'que haces', 'ayuda', 'test', 'prueba'
        ];

        foreach ($noise as $phrase) {
            if ($normalized === $phrase || (strlen($normalized) <= 35 && $this->containsWholePhrase($normalized, $phrase))) {
                return [
                    'store' => false,
                    'type' => 'noise',
                    'importance' => 1,
                    'reason' => 'greeting_or_non_memory_input',
                ];
            }
        }

        if ($this->containsAny($normalized, ['olvida', 'borra', 'elimina de memoria', 'no recuerdes'])) {
            return [
                'store' => false,
                'type' => 'forget_request',
                'importance' => 8,
                'reason' => 'forget_requests_are_commands_not_memories',
            ];
        }

        if ($this->containsAny($normalized, [
            'resumen', 'resumeme', 'resume el', 'resume la', 'haz un resumen',
            'sinopsis', 'de que trata', 'summary', 'summarize'
        ])) {
            return [
                'store' => true,
                'store_response' => true,
                'type' => 'knowledge_summary',
                'importance' => 6,
                'reason' => 'reusable_generated_knowledge',
            ];
        }

        if ($this->containsAny($normalized, ['recuerda', 'guarda en memoria', 'memoria:', 'memory:'])) {
            return [
                'store' => true,
                'type' => 'explicit_memory',
                'importance' => 9,
                'reason' => 'explicit_memory_instruction',
            ];
        }

        if ($this->containsAny($normalized, ['mi proyecto', 'se llama', 'jah', 'datacoreturbo', 'memorypyramid', 'actionscript php', 'qwen cloud'])) {
            return [
                'store' => true,
                'type' => 'project_fact',
                'importance' => 8,
                'reason' => 'project_or_stack_fact',
            ];
        }

        if ($this->containsAny($normalized, ['prefiero', 'me gusta', 'no me gusta', 'quiero que', 'mi estilo', 'responde', 'uso ', 'utilizo', 'trabajo con'])) {
            return [
                'store' => true,
                'type' => 'user_preference',
                'importance' => 7,
                'reason' => 'preference_or_workflow_signal',
            ];
        }

        if (strlen($normalized) >= 80 && !$this->looksLikeQuestionOnly($normalized)) {
            return [
                'store' => true,
                'type' => 'long_context',
                'importance' => 5,
                'reason' => 'long_context_with_possible_future_value',
            ];
        }

        return [
            'store' => false,
            'type' => 'transient_message',
            'importance' => 2,
            'reason' => 'not_relevant_for_persistent_memory',
        ];
    }

    private function normalizeText(string $text): string
    {
        $text = strtolower(trim($text));
        $text = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
            '¿' => '', '?' => '', '¡' => '', '!' => '', '.' => '', ',' => '', ';' => '', ':' => '',
        ]);
        return preg_replace('/\s+/', ' ', $text) ?: '';
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function containsWholePhrase(string $text, string $phrase): bool
    {
        return preg_match('/(?:^|\s)' . preg_quote($phrase, '/') . '(?:$|\s)/u', $text) === 1;
    }

    private function looksLikeQuestionOnly(string $text): bool
    {
        return str_starts_with($text, 'que ')
            || str_starts_with($text, 'como ')
            || str_starts_with($text, 'cual ')
            || str_starts_with($text, 'cuando ')
            || str_starts_with($text, 'donde ')
            || str_starts_with($text, 'por que ')
            || str_starts_with($text, 'quien ');
    }
}
