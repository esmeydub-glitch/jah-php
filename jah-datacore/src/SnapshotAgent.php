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
        $snapshot = [];
        $results = $storage->query($collection, fn($d) => true);
        $snapshot['records'] = $results;
        $snapshot['created_at'] = time();

        $file = $this->basePath . "/{$collection}_" . time() . ".json";
        file_put_contents($file, json_encode($snapshot, JSON_UNESCAPED_UNICODE));

        return $file;
    }

    public function restore(string $snapshotFile, StorageAgent $storage): bool
    {
        if (!file_exists($snapshotFile)) {
            return false;
        }

        $snapshot = json_decode(file_get_contents($snapshotFile), true);
        foreach ($snapshot['records'] ?? [] as $record) {
            $storage->insert('restored_' . time(), $record);
        }

        return true;
    }

    public function listSnapshots(string $collection): array
    {
        return glob($this->basePath . "/{$collection}_*.json") ?: [];
    }
}