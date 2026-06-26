<?php

declare(strict_types=1);

namespace Jah;

use Generator;

final class JasAsyncActions
{
    private array $workers = [];
    private int $maxWorkers = 4;

    public function addWorker(callable $task): void
    {
        $this->workers[] = $task;
    }

    public function runAll(): array
    {
        $results = [];
        foreach ($this->workers as $i => $worker) {
            $results[$i] = $worker();
        }
        return $results;
    }

    public function stream(Generator $gen): Generator
    {
        while ($gen->valid()) {
            yield $gen->current();
            $gen->next();
        }
    }
}