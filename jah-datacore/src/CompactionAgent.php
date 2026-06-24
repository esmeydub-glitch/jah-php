<?php

declare(strict_types=1);

namespace Jah\DataCore;

final class CompactionAgent
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function compact(string $collection): int
    {
        $dataDir = $this->basePath . '/data';
        $cacheDir = $this->basePath . '/cache';

        $documents = [];
        foreach (glob("{$dataDir}/{$collection}_*.ndjson") as $file) {
            foreach (file($file) as $line) {
                $record = json_decode($line, true);
                if ($record && isset($record['payload'])) {
                    $id = $record['payload']['id'] ?? null;
                    if ($id && !isset($record['payload']['_deleted'])) {
                        $documents[$id] = $record['payload'];
                    }
                }
            }
            @unlink($file);
        }

        // Escribir compactado
        $compacted = "{$dataDir}/{$collection}_compact.ndjson";
        $fd = fopen($compacted, 'w');
        foreach ($documents as $doc) {
            fwrite($fd, json_encode($doc) . "\n");
        }
        fclose($fd);

        // Rebuild índice
        $this->rebuildIndex($collection, $documents);

        return count($documents);
    }

    private function rebuildIndex(string $collection, array $docs): void
    {
        $indexFile = $this->basePath . "/{$collection}.idx";
        $idx = [];
        $line = 0;
        foreach ($docs as $id => $doc) {
            $idx[$id] = $line++;
        }
        file_put_contents($indexFile, json_encode($idx));
    }
}