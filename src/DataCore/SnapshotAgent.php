<?php

declare(strict_types=1);

namespace Jah\DataCore;

final class SnapshotAgent
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0700, true);
        }
    }

    public function create(string $collection, StorageAgent $storage): string
    {
        $collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', $collection) ?: 'default';
        $snapshot = [];
        $results = $storage->query($collection, fn($d) => true);
        $snapshot['collection'] = $collection;
        $snapshot['records'] = $results;
        $snapshot['created_at'] = time();

        $file = $this->basePath . "/{$collection}_" . time() . ".jahp";
        file_put_contents($file, PhpSerializer::encode($snapshot));

        return $file;
    }

    public function restore(string $snapshotFile, StorageAgent $storage): bool
    {
        if (!file_exists($snapshotFile)) {
            return false;
        }

        $snapshot = PhpSerializer::decode(file_get_contents($snapshotFile), true);
        $collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string)($snapshot['collection'] ?? 'restored')) ?: 'restored';
        foreach ($snapshot['records'] ?? [] as $record) {
            $storage->insert($collection, $record);
        }

        return true;
    }

    public function listSnapshots(string $collection): array
    {
        $collection = preg_replace('/[^a-zA-Z0-9_-]/', '_', $collection) ?: 'default';
        return glob($this->basePath . "/{$collection}_*.jahp") ?: [];
    }
}
