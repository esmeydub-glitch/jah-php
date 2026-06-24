<?php

declare(strict_types=1);

namespace Jah\DataCore;

/**
 * LockAgent - Bloqueo de recursos con timeout
 */
final class LockAgent
{
    private string $basePath;
    private array $locks = [];

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function acquire(string $resource, int $timeoutMs = 5000): void
    {
        $lockFile = $this->basePath . "/{$resource}.lock";
        $fp = fopen($lockFile, 'w');
        if ($fp === false) {
            throw new \RuntimeException("Cannot create lock: {$resource}");
        }

        $start = hrtime(true);
        while (!flock($fp, LOCK_EX | LOCK_NB)) {
            usleep(10000);
            if ((hrtime(true) - $start) / 1_000_000 > $timeoutMs) {
                fclose($fp);
                throw new \RuntimeException("Lock timeout: {$resource}");
            }
        }

        $this->locks[$resource] = $fp;
    }

    public function release(string $resource): void
    {
        if (isset($this->locks[$resource])) {
            flock($this->locks[$resource], LOCK_UN);
            fclose($this->locks[$resource]);
            unlink($this->basePath . "/{$resource}.lock");
            unset($this->locks[$resource]);
        }
    }

    public function releaseAll(): void
    {
        foreach (array_keys($this->locks) as $resource) {
            $this->release($resource);
        }
    }
}

/**
 * IndexAgent - Índices por campo
 */
final class IndexAgent
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function build(string $collection, string $field): void
    {
        $indexFile = $this->basePath . "/{$collection}_{$field}.idx";
        $entries = [];

        foreach (glob($this->basePath . "/../data/{$collection}_*.ndjson") as $file) {
            foreach (file($file) as $line) {
                $record = json_decode($line, true);
                if ($record && isset($record['payload'][$field])) {
                    $entries[$record['id']] = $record['payload'][$field];
                }
            }
        }

        file_put_contents($indexFile, json_encode($entries));
    }
}

/**
 * EventAgent - Event sourcing con hash chain
 */
final class EventAgent
{
    private string $basePath;
    private ?string $lastHash = null;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function emit(string $type, string $collection, array $payload): void
    {
        $event = [
            'id' => bin2hex(random_bytes(12)),
            'type' => $type,
            'collection' => $collection,
            'payload' => $payload,
            'ts' => time(),
            'prev_hash' => $this->lastHash,
        ];

        $event['hash'] = hash('sha256', json_encode($event));
        $this->lastHash = $event['hash'];

        file_put_contents(
            $this->basePath . "/{$collection}.events",
            json_encode($event) . "\n",
            FILE_APPEND
        );
    }
}

/**
 * TransactionAgent - Journal de transacciones
 */
final class TransactionAgent
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function begin(string $txId): void
    {
        $journal = $this->basePath . "/pending/{$txId}.journal";
        file_put_contents($journal, json_encode(['status' => 'started', 'ts' => time()]));
    }

    public function commit(string $txId): bool
    {
        $pending = $this->basePath . "/pending/{$txId}.journal";
        $committed = $this->basePath . "/committed/{$txId}.journal";

        rename($pending, $committed);
        return true;
    }

    public function rollback(string $txId): void
    {
        $pending = $this->basePath . "/pending/{$txId}.journal";
        $rollback = $this->basePath . "/rollback/{$txId}.journal";
        rename($pending, $rollback);
    }
}

/**
 * SchemaAgent - Definición de colecciones
 */
final class SchemaAgent
{
    private string $basePath;
    private string $collection = '';

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function define(string $name): self
    {
        $this->collection = $name;
        $this->ensureCollection($name);
        return $this;
    }

    private function ensureCollection(string $name): void
    {
        $schemaFile = $this->basePath . "/{$name}.json";
        if (!file_exists($schemaFile)) {
            file_put_contents($schemaFile, json_encode([
                'name' => $name,
                'fields' => [],
                'created_at' => time(),
            ]));
        }
    }

    public function addField(string $field, string $type): self
    {
        $schema = json_decode(file_get_contents($this->basePath . "/{$this->collection}.json"), true);
        $schema['fields'][$field] = $type;
        file_put_contents($this->basePath . "/{$this->collection}.json", json_encode($schema));
        return $this;
    }
}

/**
 * IntegrityAgent - Hash chain y checksums
 */
final class IntegrityAgent
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function verify(string $collection): array
    {
        $issues = [];
        $files = glob($this->basePath . "/../data/{$collection}_*.ndjson");

        foreach ($files as $file) {
            $lines = file($file);
            foreach ($lines as $num => $line) {
                $record = json_decode($line, true);
                if ($record) {
                    $expected = hash('sha256', json_encode($record['payload']));
                    if (!isset($record['hash']) || $record['hash'] !== $expected) {
                        $issues[] = "{$file}:{$num}";
                    }
                }
            }
        }

        return $issues;
    }
}