<?php

declare(strict_types=1);

namespace Jah\DataCore;

use RuntimeException;

/**
 * SwooleWorkerPool - Pool de workers para procesamiento paralelo
 */
final class SwooleWorkerPool
{
    private \Swoole\Process\Pool $pool;
    private int $workerCount;

    public function __construct(int $workerCount = 4)
    {
        $this->workerCount = $workerCount;
        $this->pool = new \Swoole\Process\Pool($workerCount);
    }

    public function start(callable $workerFn): void
    {
        $this->pool->on('workerstart', function (\Swoole\Process $worker) use ($workerFn) {
            $workerFn($worker);
        });

        $this->pool->start();
    }

    public static function available(): bool
    {
        return extension_loaded('swoole');
    }
}