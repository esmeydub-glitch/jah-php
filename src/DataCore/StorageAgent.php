<?php

declare(strict_types=1);

namespace Jah\DataCore;

/**
 * StorageAgent - Almacenamiento append-only con segmentos
 */
final class StorageAgent
{
    private string $basePath;
    private int $segmentSize = 10000;
    private array $indexes = [];

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function insert(string $collection, array $doc): string
    {
        $id = $doc['id'] ?? bin2hex(random_bytes(16));
        if (!isset($doc['id'])) {
            $doc['id'] = $id;
            $doc['_ts'] = time();
        }

        $segment = $this->getSegment($collection, $id);
        $lineOffset = $this->getLineOffset($collection, $segment);

        $record = PhpSerializer::encode([
            'id' => $id,
            'collection' => $collection,
            'payload' => $doc,
            'ts' => $doc['_ts'] ?? time(),
        ]) . "\n";

        $bytes = file_put_contents(
            $this->basePath . "/{$collection}_{$segment}.jahl",
            $record,
            FILE_APPEND
        );

        // Índice simple: id => archivo:linea
        $this->indexRecord($collection, $id, $segment, $lineOffset);

        return $id;
    }

    public function find(string $collection, string $id): ?array
    {
        $idx = $this->loadIndex($collection);
        if (!isset($idx[$id])) {
            return null;
        }

        [$segment, $line] = $idx[$id];
        $file = $this->basePath . "/{$collection}_{$segment}.jahl";

        if (!file_exists($file)) {
            return null;
        }

        $lines = file($file);
        $record = PhpSerializer::decode($lines[$line] ?? '{}', true);

        $payload = $record['payload'] ?? null;
        if (!is_array($payload) || ($payload['_deleted'] ?? false) === true) {
            return null;
        }

        return $payload;
    }

    public function query(string $collection, callable $filter): array
    {
        $latest = [];
        foreach (glob($this->basePath . "/{$collection}_*.jahl") as $file) {
            foreach (file($file) as $line) {
                $record = PhpSerializer::decode($line, true);
                if (is_array($record) && isset($record['id'], $record['payload']) && is_array($record['payload'])) {
                    $latest[(string) $record['id']] = $record['payload'];
                }
            }
        }

        $results = [];
        foreach ($latest as $payload) {
            if (($payload['_deleted'] ?? false) !== true && $filter($payload)) {
                $results[] = $payload;
            }
        }
        return $results;
    }

    public function update(string $collection, string $id, array $patch): bool
    {
        $doc = $this->find($collection, $id);
        if ($doc === null) {
            return false;
        }

        $this->insert($collection, array_merge($doc, $patch));
        return true;
    }

    public function markDeleted(string $collection, string $id): bool
    {
        $doc = $this->find($collection, $id);
        if ($doc === null) {
            return false;
        }

        $doc['_deleted'] = true;
        $this->insert($collection, $doc);
        return true;
    }

    private function getSegment(string $collection, string $id): int
    {
        return (int) ((crc32($id) % 1000000) / $this->segmentSize);
    }

    private function getLineOffset(string $collection, int $segment): int
    {
        $file = $this->basePath . "/{$collection}_{$segment}.jahl";
        return file_exists($file) ? count(file($file)) : 0;
    }

    private function indexRecord(string $collection, string $id, int $segment, int $line): void
    {
        $indexFile = $this->basePath . "/{$collection}.idx";
        file_put_contents($indexFile, "{$id}:{$segment}:{$line}\n", FILE_APPEND);
        $this->indexes[$collection][$id] = [$segment, $line];
    }

    private function loadIndex(string $collection): array
    {
        if (isset($this->indexes[$collection])) {
            return $this->indexes[$collection];
        }

        $idx = [];
        $file = $this->basePath . "/{$collection}.idx";
        if (file_exists($file)) {
            foreach (file($file) as $line) {
                $parts = explode(':', trim($line));
                if (count($parts) === 3) {
                    $idx[$parts[0]] = [(int) $parts[1], (int) $parts[2]];
                }
            }
        }
        return $this->indexes[$collection] = $idx;
    }

    public function getStats(): array
    {
        $stats = [];
        foreach (glob($this->basePath . "/*.jahl") as $file) {
            $basename = basename($file, '.jahl');
            $collection = explode('_', $basename)[0];
            $stats[$collection] = ($stats[$collection] ?? 0) + count(file($file));
        }
        return $stats;
    }

    public function close(): void
    {
        $this->indexes = [];
    }
}
