<?php

declare(strict_types=1);

namespace Jah\DataCore;

/**
 * DataCoreTurbo
 * Pure PHP binary append-only storage for the JAH MemoryAgent.
 *
 * Record format:
 * [4 bytes little-endian length][JSON payload][newline]
 *
 * Index format:
 * id:segment:offset:timestamp
 */
final class DataCoreTurbo
{
    private string $basePath;
    private array $buffer = [];
    private int $batchSize;

    public function __construct(string $basePath, int $batchSize = 1000)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->batchSize = max(1, $batchSize);
        $this->initDirs();
    }

    private function initDirs(): void
    {
        foreach (['data', 'index', 'wal'] as $dir) {
            $path = "{$this->basePath}/{$dir}";
            if (!is_dir($path)) {
                mkdir($path, 0700, true);
            }
        }
    }

    public function insert(string $collection, array $doc): string
    {
        $collection = $this->sanitizeCollection($collection);
        $id = (string)($doc['id'] ?? bin2hex(random_bytes(8)));
        $doc['id'] = $id;
        $doc['_ts'] = $doc['_ts'] ?? time();

        $this->buffer[] = ['collection' => $collection, 'doc' => $doc, 'id' => $id];

        if (count($this->buffer) >= $this->batchSize) {
            $this->flush();
        }

        return $id;
    }

    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $batch = $this->buffer;
        $this->buffer = [];

        foreach ($batch as $entry) {
            $this->writeBinary($entry['collection'], $entry['doc'], $entry['id']);
        }
    }

    private function writeBinary(string $collection, array $doc, string $id): void
    {
        $segment = $this->segmentForId($id);
        $file = "{$this->basePath}/data/{$collection}_{$segment}.bin";
        $indexFile = "{$this->basePath}/index/{$collection}.idx";

        $json = json_encode(['id' => $id, 'payload' => $doc], JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }

        $record = pack('V', strlen($json)) . $json . "\n";

        $offset = is_file($file) ? (int) filesize($file) : 0;
        $handle = fopen($file, 'ab');
        if ($handle === false) {
            return;
        }

        flock($handle, LOCK_EX);
        fwrite($handle, $record);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        file_put_contents($indexFile, "{$id}:{$segment}:{$offset}:" . time() . "\n", FILE_APPEND | LOCK_EX);
    }

    public function find(string $collection, string $id): ?array
    {
        $this->flush();
        $collection = $this->sanitizeCollection($collection);
        $indexFile = "{$this->basePath}/index/{$collection}.idx";
        if (!is_file($indexFile)) {
            return null;
        }

        $foundSegment = null;
        $foundOffset = null;

        $handle = fopen($indexFile, 'r');
        if ($handle === false) {
            return null;
        }

        while (($line = fgets($handle)) !== false) {
            $parts = explode(':', trim($line));
            if (($parts[0] ?? '') === $id) {
                $foundSegment = (int)($parts[1] ?? 0);
                $foundOffset = isset($parts[2]) ? (int)$parts[2] : null;
            }
        }
        fclose($handle);

        if ($foundSegment === null) {
            return null;
        }

        $payload = $this->readBinary($collection, $foundSegment, $id, $foundOffset);
        if (($payload['_deleted'] ?? false) === true) {
            return null;
        }

        return $payload;
    }

    private function readBinary(string $collection, int $segment, string $targetId, ?int $offset = null): ?array
    {
        $file = "{$this->basePath}/data/{$collection}_{$segment}.bin";
        if (!is_file($file)) {
            return null;
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return null;
        }

        if ($offset !== null && $offset >= 0) {
            fseek($handle, $offset);
            $payload = $this->readOneRecord($handle, $targetId);
            fclose($handle);
            return $payload;
        }

        $latest = null;
        while (!feof($handle)) {
            $payload = $this->readOneRecord($handle, $targetId);
            if ($payload !== null) {
                $latest = $payload;
            }
        }
        fclose($handle);

        return $latest;
    }

    private function readOneRecord($handle, ?string $targetId = null): ?array
    {
        $lenData = fread($handle, 4);
        if ($lenData === false || strlen($lenData) < 4) {
            return null;
        }

        $unpacked = unpack('V', $lenData);
        $len = (int)($unpacked[1] ?? 0);
        if ($len <= 0) {
            return null;
        }

        $json = fread($handle, $len);
        fgetc($handle);

        if ($json === false || strlen($json) !== $len) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['payload'])) {
            return null;
        }

        if ($targetId !== null && (string)($data['id'] ?? '') !== $targetId) {
            return null;
        }

        return is_array($data['payload']) ? $data['payload'] : null;
    }

    public function batchInsert(string $collection, array $docs): int
    {
        $collection = $this->sanitizeCollection($collection);
        $count = 0;

        foreach ($docs as $doc) {
            if (!is_array($doc)) {
                continue;
            }
            $this->writeBinary($collection, $this->normalizeDoc($doc), (string)($doc['id'] ?? bin2hex(random_bytes(8))));
            $count++;
        }

        return $count;
    }

    public function query(string $collection, callable $filter): array
    {
        $this->flush();
        $collection = $this->sanitizeCollection($collection);
        $latestById = [];

        foreach (glob("{$this->basePath}/data/{$collection}_*.bin") ?: [] as $file) {
            $handle = fopen($file, 'rb');
            if ($handle === false) {
                continue;
            }

            while (!feof($handle)) {
                $payload = $this->readOneRecord($handle);
                if ($payload === null) {
                    continue;
                }
                $id = (string)($payload['id'] ?? '');
                if ($id !== '') {
                    $latestById[$id] = $payload;
                }
            }
            fclose($handle);
        }

        $results = [];
        foreach ($latestById as $payload) {
            if (($payload['_deleted'] ?? false) === true) {
                continue;
            }
            if ($filter($payload)) {
                $results[] = $payload;
            }
        }

        return $results;
    }

    public function delete(string $collection, string $id): void
    {
        $this->insert($collection, ['id' => $id, '_deleted' => true, '_ts' => time()]);
        $this->flush();
    }

    public function getStats(): array
    {
        $this->flush();
        $records = 0;
        $collections = [];

        foreach (glob("{$this->basePath}/data/*.bin") ?: [] as $file) {
            $name = basename($file);
            $collection = preg_replace('/_\d+\.bin$/', '', $name) ?: 'unknown';
            $collections[$collection] = true;

            $handle = fopen($file, 'rb');
            if ($handle === false) {
                continue;
            }
            while (!feof($handle)) {
                $lenData = fread($handle, 4);
                if ($lenData === false || strlen($lenData) < 4) {
                    break;
                }
                $len = (int)(unpack('V', $lenData)[1] ?? 0);
                fseek($handle, $len + 1, SEEK_CUR);
                $records++;
            }
            fclose($handle);
        }

        return [
            'records' => $records,
            'documents' => $records,
            'collections' => count($collections),
            'buffered' => count($this->buffer),
        ];
    }

    public function close(): void
    {
        $this->flush();
    }

    private function normalizeDoc(array $doc): array
    {
        $id = (string)($doc['id'] ?? bin2hex(random_bytes(8)));
        $doc['id'] = $id;
        $doc['_ts'] = $doc['_ts'] ?? time();
        return $doc;
    }

    private function segmentForId(string $id): int
    {
        return (int)(crc32($id) % 1000);
    }

    private function sanitizeCollection(string $collection): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_-]/', '_', $collection) ?: 'memories';
        return trim($clean, '_') !== '' ? $clean : 'memories';
    }
}
