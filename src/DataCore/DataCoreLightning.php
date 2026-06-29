<?php

declare(strict_types=1);

namespace Jah\DataCore;

use RuntimeException;

/**
 * DataCore Lightning - Escritura máxima velocidad con batch
 */
final class DataCoreLightning
{
    private string $basePath;
    private array $buckets = [];
    private int $batchSize = 5000;

    public function __construct(string $basePath, int $batchSize = 5000)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->batchSize = $batchSize;
        $this->init();
    }

    private function init(): void
    {
        foreach (['data', 'index', 'wal'] as $dir) {
            is_dir("{$this->basePath}/{$dir}") || mkdir("{$this->basePath}/{$dir}", 0700, true);
        }
    }

    public static function open(string $path, int $batchSize = 5000): self
    {
        return new self($path, $batchSize);
    }

    public function insert(string $collection, array $doc): string
    {
        $id = $doc['id'] ?? bin2hex(random_bytes(8));
        $doc['id'] ??= $id;
        $doc['_ts'] = time();

        // Batch buffer
        if (!isset($this->buckets[$collection])) {
            $this->buckets[$collection] = ['data' => [], 'index' => []];
        }

        $this->buckets[$collection]['data'][] = PhpSerializer::encode(['id' => $id, 'payload' => $doc]);
        $this->buckets[$collection]['index'][] = "{$id}\n";

        // Flush when reach batch
        if (count($this->buckets[$collection]['data']) >= $this->batchSize) {
            $this->flushBucket($collection);
        }

        return $id;
    }

    public function flushAll(): void
    {
        foreach (array_keys($this->buckets) as $collection) {
            $this->flushBucket($collection);
        }
    }

    private function flushBucket(string $collection): void
    {
        if (empty($this->buckets[$collection]['data'])) {
            return;
        }

        $file = "{$this->basePath}/data/{$collection}.jahl";
        file_put_contents($file, implode("\n", $this->buckets[$collection]['data']) . "\n", FILE_APPEND);

        $indexFile = "{$this->basePath}/index/{$collection}.idx";
        file_put_contents($indexFile, implode('', $this->buckets[$collection]['index']), FILE_APPEND);

        $this->buckets[$collection] = ['data' => [], 'index' => []];
    }

    public function query(string $collection, callable $filter): array
    {
        $this->flushBucket($collection);

        $file = "{$this->basePath}/data/{$collection}.jahl";
        if (!is_file($file)) {
            return [];
        }

        $latest = [];
        foreach (file($file) as $line) {
            $record = PhpSerializer::decode($line, true);
            if (is_array($record) && isset($record['id'], $record['payload']) && is_array($record['payload'])) {
                $latest[(string) $record['id']] = $record['payload'];
            }
        }

        return array_values(array_filter(
            $latest,
            static fn(array $payload): bool => ($payload['_deleted'] ?? false) !== true && $filter($payload)
        ));
    }

    public function getStats(): array
    {
        $stats = [];
        foreach (glob("{$this->basePath}/data/*.jahl") as $file) {
            $lines = count(file($file));
            $collection = basename($file, '.jahl');
            $stats[$collection] = $lines;
        }
        return $stats;
    }

    public function close(): void
    {
        $this->flushAll();
    }
}
