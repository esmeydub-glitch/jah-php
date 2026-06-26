<?php

declare(strict_types=1);

namespace Jah\DataCore;

final class IntegrityAgent
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function verify(string $collection): array
    {
        $issues = [];
        $dataDir = dirname($this->basePath) . '/data';
        $files = glob("{$dataDir}/{$collection}_*.ndjson");

        foreach ($files as $file) {
            $lines = file($file);
            $lineNum = 0;
            foreach ($lines as $line) {
                $record = json_decode($line, true);
                if ($record) {
                    $expected = hash('sha256', json_encode($record['payload']));
                    if (!isset($record['hash']) || $record['hash'] !== $expected) {
                        $issues[] = "{$file}:{$lineNum}";
                    }
                }
                $lineNum++;
            }
        }

        return $issues;
    }

    public function checkpoint(): void
    {
        // Marca punto de integridad
        file_put_contents(
            $this->basePath . '/.checkpoint',
            json_encode(['ts' => time(), 'hash' => bin2hex(random_bytes(8))])
        );
    }
}