<?php

declare(strict_types=1);

namespace Jah\DataCore;

use RuntimeException;

/**
 * BufferQueue - Escritura en bloque con flush inteligente
 */
final class BufferQueue
{
    private string $dataDir;
    private string $indexDir;
    private int $flushBytes = 1048576; // 1MB
    private int $flushLines = 10000;
    private int $lineCount = 0;
    private int $byteCount = 0;
    private array $writes = [];
    private array $indexEntries = [];
    private int $segment = 0;
    private string $collection = '';

    public function __construct(string $basePath, string $collection, int $flushBytes = 1048576)
    {
        $this->dataDir = "{$basePath}/data";
        $this->indexDir = "{$basePath}/index";
        $this->collection = $collection;
        $this->flushBytes = $flushBytes;

        foreach (['data', 'index'] as $dir) {
            if (!is_dir("{$basePath}/{$dir}")) {
                mkdir("{$basePath}/{$dir}", 0700, true);
            }
        }
    }

    public function push(array $doc): string
    {
        $id = $doc['id'] ?? bin2hex(random_bytes(8));
        $doc['id'] ??= $id;
        $doc['_ts'] = time();

        $record = PhpSerializer::encode(['id' => $id, 'payload' => $doc]) . "\n";
        $bytes = strlen($record);

        $this->writes[] = $record;
        $this->indexEntries[] = "{$id}:{$this->segment}:" . ($this->lineCount) . "\n";
        
        $this->lineCount++;
        $this->byteCount += $bytes;

        if ($this->byteCount >= $this->flushBytes || $this->lineCount >= $this->flushLines) {
            $this->flush();
        }

        return $id;
    }

    public function flush(): void
    {
        if (empty($this->writes)) {
            return;
        }

        $file = "{$this->dataDir}/{$this->collection}_{$this->segment}.jahl";
        file_put_contents($file, implode('', $this->writes), FILE_APPEND);

        // Flush index in batch
        $indexFile = "{$this->indexDir}/{$this->collection}.idx";
        file_put_contents($indexFile, implode('', $this->indexEntries), FILE_APPEND);

        $this->writes = [];
        $this->indexEntries = [];
        $this->byteCount = 0;
        $this->lineCount = 0;
        $this->segment++;
    }

    public function getStats(): array
    {
        return [
            'lines_buffered' => $this->lineCount,
            'bytes_buffered' => $this->byteCount,
            'segment' => $this->segment,
        ];
    }
}

/**
 * DataCoreUltra - Versión con buffer ultra-rapida
 */
final class DataCoreUltra
{
    private string $basePath;
    private array $queues = [];
    private int $flushBytes;

    public function __construct(string $basePath, int $flushBytes = 1048576)
    {
        $this->basePath = $basePath;
        $this->flushBytes = max(1, $flushBytes);
        $this->initDirs();
    }

    private function initDirs(): void
    {
        foreach (['data', 'index', 'wal', 'cache'] as $dir) {
            if (!is_dir("{$this->basePath}/{$dir}")) {
                mkdir("{$this->basePath}/{$dir}", 0700, true);
            }
        }
    }

    public static function open(string $path, int $flushBytes = 1048576): self
    {
        return new self($path, $flushBytes);
    }

    public function getQueue(string $collection): BufferQueue
    {
        if (!isset($this->queues[$collection])) {
            $this->queues[$collection] = new BufferQueue($this->basePath, $collection, $this->flushBytes);
        }
        return $this->queues[$collection];
    }

    public function insert(string $collection, array $doc): string
    {
        return $this->getQueue($collection)->push($doc);
    }

    public function flush(): void
    {
        foreach ($this->queues as $queue) {
            $queue->flush();
        }
    }

    public function getStats(): array
    {
        $stats = [];
        foreach ($this->queues as $col => $queue) {
            $stats[$col] = $queue->getStats();
        }
        return $stats;
    }
}
