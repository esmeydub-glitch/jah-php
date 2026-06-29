<?php

declare(strict_types=1);

namespace Jah\DataCore;

final class EventAgent
{
    private string $basePath;
    private ?string $lastHash = null;

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function emit(string $type, string $collection, array $payload): void
    {
        $event = [
            'id' => bin2hex(random_bytes(12)),
            'type' => $type,
            'collection' => $collection,
            'payload' => $payload,
            'ts' => time(),
            'prev_hash' => $this->lastHash,
        ];

        $event['hash'] = hash('sha256', PhpSerializer::encode($event));
        $this->lastHash = $event['hash'];

        file_put_contents(
            $this->basePath . "/{$collection}.events",
            PhpSerializer::encode($event) . "\n",
            FILE_APPEND
        );
    }
}