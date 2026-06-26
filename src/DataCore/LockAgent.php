<?php

declare(strict_types=1);

namespace Jah\DataCore;

use RuntimeException;

final class LockAgent
{
    private string $basePath;
    private array $locks = [];

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        if (!is_dir($basePath)) {
            mkdir($basePath, 0700, true);
        }
    }

    public function acquire(string $resource, int $timeoutMs = 5000): void
    {
        $lockFile = $this->basePath . "/{$resource}.lock";
        $fp = fopen($lockFile, 'w');
        if ($fp === false) {
            throw new RuntimeException("Cannot create lock: {$resource}");
        }

        $start = hrtime(true);
        while (!flock($fp, LOCK_EX | LOCK_NB)) {
            usleep(10000);
            if ((hrtime(true) - $start) / 1_000_000 > $timeoutMs) {
                fclose($fp);
                throw new RuntimeException("Lock timeout: {$resource}");
            }
        }

        $this->locks[$resource] = $fp;
    }

    public function release(string $resource): void
    {
        if (isset($this->locks[$resource])) {
            flock($this->locks[$resource], LOCK_UN);
            fclose($this->locks[$resource]);
            @unlink($this->basePath . "/{$resource}.lock");
            unset($this->locks[$resource]);
        }
    }
}