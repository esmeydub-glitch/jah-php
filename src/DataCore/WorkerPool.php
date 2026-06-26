<?php

declare(strict_types=1);

namespace Jah\DataCore;

/**
 * WorkerPool - Pool genérico de procesos (con o sin Swoole)
 */
final class WorkerPool
{
    private int $workers;
    private string $basePath;
    private array $processes = [];

    public function __construct(string $basePath, int $workers = 4)
    {
        $this->basePath = $basePath;
        $this->workers = $workers;
    }

    public function parallelInsert(string $collection, array $docs): int
    {
        if (SwooleWorkerPool::available()) {
            return $this->swooleInsert($collection, $docs);
        }

        return $this->processInsert($collection, $docs);
    }

    private function swooleInsert(string $collection, array $docs): int
    {
        $chunkSize = (int) ceil(count($docs) / $this->workers);
        $total = 0;

        $pool = new \Swoole\Process\Pool($this->workers);
        $sharedData = [];

        $pool->on('workerstart', function (\Swoole\Process $worker) use ($collection, $docs, $chunkSize) {
            $start = $worker->id * $chunkSize;
            $end = min($start + $chunkSize, count($docs));
            $chunk = array_slice($docs, $start, $chunkSize);

            $storage = new StorageAgent($this->basePath . '/data');
            foreach ($chunk as $doc) {
                $storage->insert($collection, $doc);
            }
            $storage->close();
        });

        $pool->start();
        return count($docs);
    }

    private function processInsert(string $collection, array $docs): int
    {
        $chunkSize = (int) ceil(count($docs) / $this->workers);
        $total = 0;

        for ($w = 0; $w < $this->workers; $w++) {
            $start = $w * $chunkSize;
            $end = min($start + $chunkSize, count($docs));
            $chunk = array_slice($docs, $start, $chunkSize);

            if (empty($chunk)) {
                continue;
            }

            $pid = pcntl_fork();
            if ($pid == -1) {
                continue;
            }

            if ($pid == 0) {
                // Child process
                $storage = new StorageAgent($this->basePath . '/data');
                foreach ($chunk as $doc) {
                    $storage->insert($collection, $doc);
                }
                exit(0);
            }

            $this->processes[] = $pid;
        }

        // Wait for all children
        foreach ($this->processes as $pid) {
            pcntl_waitpid($pid, $status);
        }

        return count($docs);
    }

    public function __destruct()
    {
        $this->processes = [];
    }
}