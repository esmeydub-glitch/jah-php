<?php

declare(strict_types=1);

namespace Jah\DataCore;

use RuntimeException;

/**
 * DataMaster - Coordinador principal de DataCore
 */
final class DataMaster
{
    private string $basePath;
    private LockAgent $lock;
    private StorageAgent $storage;
    private IndexAgent $index;
    private EventAgent $events;
    private TransactionAgent $tx;
    private SchemaAgent $schema;
    private IntegrityAgent $integrity;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->initDirs();

        $this->lock = new LockAgent($this->basePath . '/locks');
        $this->storage = new StorageAgent($this->basePath . '/data');
        $this->index = new IndexAgent($this->basePath . '/index');
        $this->events = new EventAgent($this->basePath . '/events');
        $this->tx = new TransactionAgent($this->basePath . '/tx');
        $this->schema = new SchemaAgent($this->basePath . '/config');
        $this->integrity = new IntegrityAgent($this->basePath . '/audit');
    }

    private function initDirs(): void
    {
        foreach (['data', 'index', 'events', 'tx', 'snapshots', 'cache', 'locks', 'audit', 'config'] as $dir) {
            $path = $this->basePath . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0700, true);
            }
        }
    }

    public function schema(string $name): SchemaAgent
    {
        return $this->schema->define($name);
    }

    public function insert(string $collection, array $doc): string
    {
        return $this->executeOperation('insert', $collection, $doc);
    }

    public function find(string $collection, string $id): ?array
    {
        return $this->executeOperation('find', $collection, ['id' => $id]);
    }

    public function query(string $collection, callable $filter): array
    {
        return $this->executeOperation('query', $collection, $filter);
    }

    public function update(string $collection, string $id, array $patch): bool
    {
        return $this->executeOperation('update', $collection, array_merge($patch, ['id' => $id]));
    }

    public function delete(string $collection, string $id): bool
    {
        return $this->executeOperation('delete', $collection, ['id' => $id]);
    }

    private function executeOperation(string $op, string $collection, mixed $data)
    {
        $this->lock->acquire($collection);
        try {
            return match ($op) {
                'insert' => $this->storage->insert($collection, $data),
                'find' => $this->storage->find($collection, $data['id']),
                'query' => $this->storage->query($collection, $data),
                'update' => $this->storage->update($collection, $data['id'], $data),
                'delete' => $this->storage->markDeleted($collection, $data['id']),
            };
        } finally {
            $this->lock->release($collection);
        }
    }

    public function getStats(): array
    {
        return $this->storage->getStats();
    }

    public function close(): void
    {
        $this->storage->close();
    }
}