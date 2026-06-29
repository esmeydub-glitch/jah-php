<?php

declare(strict_types=1);

namespace Jah\DataCore;

final class TransactionAgent
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        foreach (['pending', 'committed', 'rollback'] as $dir) {
            if (!is_dir("{$this->basePath}/{$dir}")) {
                mkdir("{$this->basePath}/{$dir}", 0700, true);
            }
        }
    }

    public function begin(string $txId): void
    {
        $journal = $this->basePath . "/pending/{$txId}.journal";
        file_put_contents($journal, PhpSerializer::encode(['status' => 'started', 'ts' => time()]));
    }

    public function commit(string $txId): bool
    {
        $pending = $this->basePath . "/pending/{$txId}.journal";
        $committed = $this->basePath . "/committed/{$txId}.journal";
        if (!file_exists($pending)) {
            return false;
        }
        rename($pending, $committed);
        return true;
    }

    public function rollback(string $txId): void
    {
        $pending = $this->basePath . "/pending/{$txId}.journal";
        $rollback = $this->basePath . "/rollback/{$txId}.journal";
        if (file_exists($pending)) {
            rename($pending, $rollback);
        }
    }
}