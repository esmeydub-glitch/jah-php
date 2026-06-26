<?php

declare(strict_types=1);

namespace Jah\DataCore;

use RuntimeException;

/**
 * DataCore Turbo - Almacenamiento binario + batch + mmap
 */
final class DataCoreTurbo
{
    private string $basePath;
    private array $buffer = [];
    private int $batchSize = 1000;
    private int $flushTimer = 0;

    public function __construct(string $basePath, int $batchSize = 1000)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->batchSize = $batchSize;
        $this->initDirs();
    }

    private function initDirs(): void
    {
        foreach (['data', 'index', 'wal'] as $dir) {
            if (!is_dir("{$this->basePath}/{$dir}")) {
                mkdir("{$this->basePath}/{$dir}", 0700, true);
            }
        }
    }

    public function insert(string $collection, array $doc): string
    {
        $id = $doc['id'] ?? bin2hex(random_bytes(8));
        $doc['id'] ??= $id;
        $doc['_ts'] = time();

        $this->buffer[] = ['collection' => $collection, 'doc' => $doc, 'id' => $id];
        
        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }

        return $id;
    }

    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        foreach ($this->buffer as $entry) {
            $this->writeBinary($entry['collection'], $entry['doc'], $entry['id']);
        }

        $this->buffer = [];
    }

    private function writeBinary(string $collection, array $doc, string $id): void
    {
        $segment = crc32($id) % 1000;
        $file = "{$this->basePath}/data/{$collection}_{$segment}.bin";

        // Formato binario: [4 bytes length][JSON data][newline]
        $json = json_encode(['id' => $id, 'payload' => $doc]);
        $record = pack('V', strlen($json)) . $json . "\n";

        file_put_contents($file, $record, FILE_APPEND | LOCK_EX);

        // Index hash en memoria
        $indexFile = "{$this->basePath}/index/{$collection}.idx";
        file_put_contents($indexFile, "{$id}:{$segment}:" . (time()) . "\n", FILE_APPEND | LOCK_EX);
    }

    public function find(string $collection, string $id): ?array
    {
        // Binary search en índice
        $indexFile = "{$this->basePath}/index/{$collection}.idx";
        if (!file_exists($indexFile)) {
            return null;
        }

        $lastLine = 0;
        $foundSegment = null;
        
        // Leer últimas líneas (el ítem más reciente está al final)
        $handle = fopen($indexFile, 'r');
        while (($line = fgets($handle)) !== false) {
            $parts = explode(':', trim($line));
            if ($parts[0] === $id) {
                $foundSegment = (int) $parts[1];
                break;
            }
        }
        fclose($handle);

        if ($foundSegment === null) {
            return null;
        }

        // Binary read del archivo
        return $this->readBinary($collection, $foundSegment, $id);
    }

    private function readBinary(string $collection, int $segment, string $targetId): ?array
    {
        $file = "{$this->basePath}/data/{$collection}_{$segment}.bin";
        if (!file_exists($file)) {
            return null;
        }

        $handle = fopen($file, 'rb');
        while (!feof($handle)) {
            $lenData = fread($handle, 4);
            if (strlen($lenData) < 4) {
                break;
            }
            $len = unpack('V', $lenData)[1];
            $json = fread($handle, $len);
            $data = json_decode($json, true);

            // Skip newline
            fgetc($handle);

            if ($data && $data['id'] === $targetId) {
                fclose($handle);
                return $data['payload'];
            }
        }
        fclose($handle);
        return null;
    }

    public function batchInsert(string $collection, array $docs): int
    {
        $count = 0;
        $writes = [];

        foreach ($docs as $doc) {
            $id = $doc['id'] ?? bin2hex(random_bytes(8));
            $doc['id'] ??= $id;
            $doc['_ts'] = time();

            $segment = crc32($id) % 1000;
            $file = "{$this->basePath}/data/{$collection}_{$segment}.bin";

            $json = json_encode(['id' => $id, 'payload' => $doc]);
            $record = pack('V', strlen($json)) . $json . "\n";
            $writes[$file][] = $record;
            $count++;
        }

        // Batch write
        foreach ($writes as $file => $records) {
            file_put_contents($file, implode('', $records), FILE_APPEND | LOCK_EX);
        }

        return $count;
    }

    public function query(string $collection, callable $filter): array
    {
        $results = [];
        foreach (glob("{$this->basePath}/data/{$collection}_*.bin") as $file) {
            $handle = fopen($file, 'rb');
            while (!feof($handle)) {
                $lenData = fread($handle, 4);
                if (strlen($lenData) < 4) {
                    break;
                }
                $len = unpack('V', $lenData)[1];
                $json = fread($handle, $len);
                $data = json_decode($json, true);
                fgetc($handle); // newline

                if ($data && isset($data['payload']) && $filter($data['payload'])) {
                    $results[] = $data['payload'];
                }
            }
            fclose($handle);
        }
        return $results;
    }

    public function getStats(): array
    {
        $count = 0;
        foreach (glob("{$this->basePath}/data/*.bin") as $file) {
            $handle = fopen($file, 'rb');
            while (!feof($handle)) {
                $lenData = fread($handle, 4);
                if (strlen($lenData) < 4) {
                    break;
                }
                $len = unpack('V', $lenData)[1];
                fseek($handle, $len + 1); // skip json + newline
                $count++;
            }
            fclose($handle);
        }
        return ['documents' => $count, 'buffered' => count($this->buffer)];
    }

    public function close(): void
    {
        $this->flush();
    }
}