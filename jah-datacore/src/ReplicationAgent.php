<?php

declare(strict_types=1);

namespace Jah\DataCore;

/**
 * ReplicationAgent - Replicación por eventos con hash chain
 */
final class ReplicationAgent
{
    private string $basePath;
    private array $nodes = [];
    private array $stats = [];

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
    }

    public function addNode(string $url): void
    {
        $this->nodes[] = $url;
    }

    public function replicate(array $event): bool
    {
        $signed = $this->signEvent($event);
        
        foreach ($this->nodes as $node) {
            $sent = $this->sendToNode($node, $signed);
            $this->stats['sent'] = ($this->stats['sent'] ?? 0) + ($sent ? 1 : 0);
            $this->stats['nodes'][] = $node;
        }

        return true;
    }

    private function signEvent(array $event): array
    {
        $event['hash'] = hash('sha256', json_encode($event));
        $event['prev_hash'] = $this->getLastHash();
        $event['signature'] = hash_hmac('sha512', json_encode($event), 'secret_key');
        return $event;
    }

    private function getLastHash(): string
    {
        $log = "{$this->basePath}/replication.log";
        if (!file_exists($log)) {
            return '';
        }

        $lines = file($log);
        $last = json_decode(end($lines), true);
        return $last['hash'] ?? '';
    }

    private function sendToNode(string $url, array $event): bool
    {
        $ch = curl_init(rtrim($url, '/') . '/event');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

        // Save to replication log
        file_put_contents(
            "{$this->basePath}/replication.log",
            json_encode($event) . "\n",
            FILE_APPEND
        );

        return $response !== false;
    }

    public function getStats(): array
    {
        return $this->stats;
    }
}