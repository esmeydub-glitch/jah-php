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
            'http' => $results = $this->collectHttp($data[0] ?? ''),
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
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return PhpSerializer::decode($response, true) ?: [];
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
            $result = match ($op) {
                'sort' => $this->sortBy($result),
                'filter' => $this->filterBy($result),
                'map' => $this->mapValues($result),
                'unique' => array_unique($result),
                'limit' => array_slice($result, 0, $op['count'] ?? 100),
                default => $result,
            };
        }

        return $result;
    }

    private function sortBy(array $data): array
    {
        usort($data, fn($a, $b) => $a <=> $b);
        return $data;
    }

    private function filterBy(array $data): array
    {
        return array_values(array_filter($data));
    }

    private function mapValues(array $data): array
    {
        return array_map(fn($x) => $x, $data);
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

        foreach ($this->sources as $field => $url) {
            $ch = curl_init(str_replace('{id}', $item['id'] ?? '', $url));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            $enriched = curl_exec($ch);
            curl_close($ch);

            if ($enriched) {
                $item[$field] = PhpSerializer::decode($enriched, true);
            }
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