<?php

declare(strict_types=1);

namespace Jah\DataCore;

/**
 * AgentFactory - Genera agentes según esquema/TTL
 */
final class AgentFactory
{
    private string $basePath;
    private array $registry = [];

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function create(string $type, array $config): AgentInterface
    {
        $agent = match ($type) {
            'collector' => new CollectorAgent($config),
            'transformer' => new TransformerAgent($config),
            'validator' => new ValidatorAgent($config),
            'enricher' => new EnricherAgent($config),
            'exporter' => new ExporterAgent($config),
            'monitor' => new MonitorAgent($config),
            'cleaner' => new CleanerAgent($config),
            'scheduler' => new SchedulerAgent($config),
            default => throw new \InvalidArgumentException("Unknown agent: {$type}"),
        };

        $id = $config['id'] ?? bin2hex(random_bytes(4));
        $this->registry[$id] = $agent;
        return $agent;
    }

    /**
     * Crea agentes en lote desde esquema PHP serializado
     */
    public static function fromSchema(string $schemaFile): self
    {
        $schema = PhpSerializer::decode(file_get_contents($schemaFile), true);
        $factory = new self(dirname($schemaFile));

        foreach ($schema['agents'] ?? [] as $agentDef) {
            $factory->create($agentDef['type'], $agentDef['config'] ?? []);
        }

        return $factory;
    }

    public function execute(string $id, mixed ...$data): mixed
    {
        return $this->registry[$id]->run(...$data);
    }

    public function list(): array
    {
        return array_keys($this->registry);
    }
}

/**
 * Interface base para todos los agentes
 */
interface AgentInterface
{
    public function run(mixed ...$data): mixed;
    public function getName(): string;
}

/**
 * CollectorAgent - Recolecta datos de múltiples fuentes
 */
final class CollectorAgent implements AgentInterface
{
    private string $source;
    private string $format;

    public function __construct(array $config)
    {
        $this->source = $config['source'] ?? 'default';
        $this->format = $config['format'] ?? 'jahp';
    }

    public function run(mixed ...$data): array
    {
        $results = [];

        match ($this->source) {
            'file' => $results = $this->collectFile($data[0] ?? ''),
            'http' => throw new \LogicException('HTTP collector disabled: QwenConnector is the only external connection'),
            'stream' => $results = $this->collectStream($data[0] ?? []),
            default => $results = $this->collectMemory($data[0] ?? []),
        };

        return $results;
    }

    private function collectFile(string $path): array
    {
        return PhpSerializer::decode(file_get_contents($path) ?: '[]', true);
    }

    private function collectHttp(string $url): array
    {
        throw new \LogicException('HTTP collector disabled: QwenConnector is the only external connection');
    }

    private function collectStream(iterable $stream): array
    {
        $results = [];
        foreach ($stream as $item) {
            $results[] = $item;
        }
        return $results;
    }

    private function collectMemory(array $data): array
    {
        return $data;
    }

    public function getName(): string { return 'collector'; }
}

/**
 * TransformerAgent - Transforma datos según pipeline
 */
final class TransformerAgent implements AgentInterface
{
    private array $pipeline;

    public function __construct(array $config)
    {
        $this->pipeline = $config['pipeline'] ?? [];
    }

    public function run(mixed ...$data): array
    {
        $result = $data[0] ?? [];

        foreach ($this->pipeline as $op) {
            $name = is_array($op) ? (string)($op['name'] ?? '') : (string)$op;
            $result = match ($name) {
                'sort' => $this->sortBy($result, is_array($op) ? $op : []),
                'filter' => $this->filterBy($result, is_array($op) ? $op : []),
                'map' => $this->mapValues($result, is_array($op) ? $op : []),
                'unique' => array_unique($result),
                'limit' => array_slice($result, 0, is_array($op) ? (int)($op['count'] ?? 100) : 100),
                default => throw new \InvalidArgumentException("Unknown transformer operation: {$name}"),
            };
        }

        return $result;
    }

    private function sortBy(array $data, array $operation): array
    {
        $field = $operation['field'] ?? null;
        $direction = strtolower((string) ($operation['direction'] ?? 'asc')) === 'desc' ? -1 : 1;
        usort($data, static function (mixed $left, mixed $right) use ($field, $direction): int {
            $a = is_string($field) && is_array($left) ? ($left[$field] ?? null) : $left;
            $b = is_string($field) && is_array($right) ? ($right[$field] ?? null) : $right;
            return ($a <=> $b) * $direction;
        });
        return $data;
    }

    private function filterBy(array $data, array $operation): array
    {
        if (isset($operation['callback']) && is_callable($operation['callback'])) {
            return array_values(array_filter($data, $operation['callback']));
        }
        if (is_string($operation['field'] ?? null)) {
            $field = $operation['field'];
            $expected = $operation['value'] ?? null;
            return array_values(array_filter(
                $data,
                static fn(mixed $row): bool => is_array($row) && ($row[$field] ?? null) === $expected
            ));
        }
        throw new \InvalidArgumentException('Filter operation requires a callback or field/value');
    }

    private function mapValues(array $data, array $operation): array
    {
        if (isset($operation['callback']) && is_callable($operation['callback'])) {
            return array_map($operation['callback'], $data);
        }
        $fields = $operation['fields'] ?? null;
        if (is_array($fields) && $fields !== []) {
            return array_map(static function (mixed $row) use ($fields): array {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('Field mapping requires array rows');
                }
                $mapped = [];
                foreach ($fields as $target => $source) {
                    if (is_int($target)) {
                        $target = (string) $source;
                    }
                    $mapped[(string) $target] = $row[(string) $source] ?? null;
                }
                return $mapped;
            }, $data);
        }
        throw new \InvalidArgumentException('Map operation requires a callback or fields map');
    }

    public function getName(): string { return 'transformer'; }
}

/**
 * ValidatorAgent - Valida contra schema
 */
final class ValidatorAgent implements AgentInterface
{
    private array $rules;

    public function __construct(array $config)
    {
        $this->rules = $config['rules'] ?? [];
    }

    public function run(mixed ...$data): array
    {
        $errors = [];
        $item = $data[0] ?? [];

        foreach ($this->rules as $field => $rule) {
            if (!isset($item[$field])) {
                $errors[] = "Missing field: {$field}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $item,
        ];
    }

    public function getName(): string { return 'validator'; }
}

/**
 * EnricherAgent - Enriquece datos con external APIs
 */
final class EnricherAgent implements AgentInterface
{
    private array $sources;

    public function __construct(array $config)
    {
        $this->sources = $config['sources'] ?? [];
    }

    public function run(mixed ...$data): array
    {
        $item = $data[0] ?? [];
        if ($this->sources !== []) {
            throw new \LogicException('External enricher disabled: QwenConnector is the only external connection');
        }
        return $item;
    }

    public function getName(): string { return 'enricher'; }
}

/**
 * ExporterAgent - Exporta a múltiples formatos
 */
final class ExporterAgent implements AgentInterface
{
    private string $format;
    private string $target;

    public function __construct(array $config)
    {
        $this->format = $config['format'] ?? 'jahp';
        $this->target = $config['target'] ?? 'file';
    }

    public function run(mixed ...$data): string
    {
        $content = match ($this->format) {
            'jahp' => PhpSerializer::encode($data[0] ?? []),
            'csv' => $this->toCsv($data[0] ?? []),
            'sql' => $this->toSql($data[0] ?? []),
            default => serialize($data[0] ?? []),
        };

        if ($this->target === 'file' && isset($data[1])) {
            file_put_contents($data[1], $content);
        }

        return $content;
    }

    private function toCsv(array $data): string
    {
        $lines = [];
        foreach ($data as $row) {
            $lines[] = implode(',', (array) $row);
        }
        return implode("\n", $lines);
    }

    private function toSql(array $data): string
    {
        return "INSERT INTO table VALUES (" . implode(',', $data) . ")";
    }

    public function getName(): string { return 'exporter'; }
}

/**
 * MonitorAgent - Monitorea métricas
 */
final class MonitorAgent implements AgentInterface
{
    private string $metric;

    public function __construct(array $config)
    {
        $this->metric = $config['metric'] ?? 'default';
    }

    public function run(mixed ...$data): array
    {
        return [
            'metric' => $this->metric,
            'value' => count($data[0] ?? []),
            'ts' => time(),
            'memory' => memory_get_usage(true),
        ];
    }

    public function getName(): string { return 'monitor'; }
}

/**
 * CleanerAgent - Limpia datos viejos
 */
final class CleanerAgent implements AgentInterface
{
    private int $ttl;

    public function __construct(array $config)
    {
        $this->ttl = $config['ttl'] ?? 86400;
    }

    public function run(mixed ...$data): int
    {
        $cleaned = 0;
        foreach ($data[0] ?? [] as $item) {
            if (($item['_ts'] ?? 0) < (time() - $this->ttl)) {
                $cleaned++;
            }
        }
        return $cleaned;
    }

    public function getName(): string { return 'cleaner'; }
}

/**
 * SchedulerAgent - Programa ejecuciones
 */
final class SchedulerAgent implements AgentInterface
{
    private int $interval;

    public function __construct(array $config)
    {
        $this->interval = $config['interval'] ?? 60;
    }

    public function run(mixed ...$data): mixed
    {
        usleep($this->interval * 1000000);
    }

    public function getName(): string { return 'scheduler'; }
}
