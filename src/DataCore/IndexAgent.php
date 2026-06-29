<?php

declare(strict_types=1);

namespace Jah\DataCore;

final class IndexAgent
{
    private string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function build(string $collection, string $field): void
    {
        $indexFile = $this->basePath . "/{$collection}_{$field}.idx";
        $entries = [];
        $dataDir = dirname($this->basePath) . '/data';

        foreach (glob("{$dataDir}/{$collection}_*.jahl") as $file) {
            foreach (file($file) as $line) {
                $record = PhpSerializer::decode($line, true);
                if ($record && isset($record['payload'][$field])) {
                    $entries[$record['id']] = $record['payload'][$field];
                }
            }
        }

        file_put_contents($indexFile, PhpSerializer::encode($entries));
    }
}