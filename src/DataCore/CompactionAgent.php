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

        $documents = [];
        $sourceFiles = glob("{$dataDir}/{$collection}_*.jahl") ?: [];
        foreach ($sourceFiles as $file) {
            foreach (file($file) as $line) {
                $record = PhpSerializer::decode($line, true);
                if ($record && isset($record['payload'])) {
                    $id = $record['payload']['id'] ?? null;
                    if (!$id) continue;
                    if (($record['payload']['_deleted'] ?? false) === true) unset($documents[$id]);
                    else $documents[$id] = $record['payload'];
                }
            }
        }

        $compacted = "{$dataDir}/{$collection}_0.jahl";
        $temporary = $compacted . '.tmp.' . bin2hex(random_bytes(4));
        $fd = fopen($temporary, 'xb');
        if ($fd === false) throw new \RuntimeException('Cannot create compacted DataCore file');
        foreach ($documents as $id => $doc) {
            $record = [
                'id' => (string)$id,
                'collection' => $collection,
                'payload' => $doc,
                'ts' => $doc['_ts'] ?? time(),
                'hash' => hash('sha256', PhpSerializer::encode($doc)),
            ];
            if (fwrite($fd, PhpSerializer::encode($record) . "\n") === false) {
                fclose($fd);
                @unlink($temporary);
                throw new \RuntimeException('Cannot write compacted DataCore file');
            }
        }
        fflush($fd);
        fclose($fd);
        if (!rename($temporary, $compacted)) {
            @unlink($temporary);
            throw new \RuntimeException('Cannot publish compacted DataCore file');
        }
        foreach ($sourceFiles as $file) {
            if ($file !== $compacted) @unlink($file);
        }

        // Rebuild índice
        $this->rebuildIndex($collection, $documents);

        return count($documents);
    }

    private function rebuildIndex(string $collection, array $docs): void
    {
        $indexDir = $this->basePath . '/index';
        if (!is_dir($indexDir)) mkdir($indexDir, 0700, true);
        $indexFile = $indexDir . "/{$collection}.idx";
        $lines = [];
        $line = 0;
        foreach ($docs as $id => $doc) {
            $lines[] = rawurlencode((string)$id) . ':0:' . $line++;
        }
        file_put_contents($indexFile, implode("\n", $lines) . ($lines !== [] ? "\n" : ''), LOCK_EX);
    }
}
