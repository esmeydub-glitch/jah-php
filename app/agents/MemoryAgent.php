<?php

declare(strict_types=1);

namespace Jah\Agents;

use Jah\Memory\Database;
use Jah\Memory\TieredMemory;

class MemoryAgent extends BaseAgent
{
    private ?Database $db = null;
    private ?TieredMemory $tieredMemory = null;

    protected function onBoot(): void
    {
        $this->subscribeToEvent('system.boot');
        $this->subscribeToEvent('gateway.validated');
        $this->subscribeToEvent('executor.started');
        $this->subscribeToEvent('executor.finished');
        $this->subscribeToEvent('analyst.report');
        $this->subscribeToEvent('system.error');
        $this->subscribeToEvent('memory.stored');
    }

    public function handle(array $event): void
    {
        $this->lazyLoadDb();
        $this->lazyLoadTieredMemory();

        if ($event['type'] === 'system.boot') {
            if ($this->db) {
                $this->log('Memory Agent conectado a base de datos.', 'info');
                $this->saveEvent($event);
            } else {
                $this->log('Memory Agent sin base de datos. Persistencia desactivada.', 'warning');
            }

            if ($this->tieredMemory) {
                $this->log('Memory Agent: Tiered memory system active.', 'info');
            }
            return;
        }

        if ($event['type'] === 'memory.stored') {
            return;
        }

        if (!$this->db && !$this->tieredMemory) {
            $this->log('No storage backend available. Ignoring event: ' . $event['type'], 'warning');
            return;
        }

        if ($this->db) {
            $this->saveEvent($event);
        }

        if ($this->tieredMemory && in_array($event['type'], ['executor.finished', 'analyst.report'])) {
            $this->tieredMemory->store('warm', $event['id'], [
                'type' => $event['type'],
                'payload' => $event['payload'],
                'source' => $event['source'],
                'timestamp' => $event['timestamp'],
                'tags' => ['event', $event['type']],
            ]);
        }

        if ($event['type'] === 'system.error' && $this->db) {
            $this->saveError(
                $event['payload']['message'] ?? 'Unknown error',
                $event['payload']['trace'] ?? '',
                $event['source']
            );

            if ($this->tieredMemory) {
                $this->tieredMemory->store('hot', 'error_' . $event['id'], [
                    'type' => 'error',
                    'message' => $event['payload']['message'] ?? 'Unknown error',
                    'tags' => ['error', 'critical'],
                ]);
            }
        }
    }

    public function saveEvent(array $event): void
    {
        if (!$this->db) {
            return;
        }

        try {
            $sql = "INSERT INTO jah_events (event_id, event_type, payload, source, created_at)
                    VALUES (:id, :type, :payload, :source, NOW())";

            $payload = json_encode($event['payload'] ?? [], JSON_THROW_ON_ERROR);

            $this->db->query($sql, [
                'id'      => $event['id'],
                'type'    => $event['type'],
                'payload' => $payload,
                'source'  => $event['source'],
            ]);
        } catch (\Throwable $e) {
            $this->log("Error al persistir evento: " . $e->getMessage(), 'error');
        }
    }

    public function saveError(string $message, string $trace, string $source): void
    {
        if (!$this->db) return;

        try {
            $sql = "INSERT INTO jah_errors (message, trace, source, created_at)
                    VALUES (:message, :trace, :source, NOW())";

            $this->db->query($sql, [
                'message' => $message,
                'trace'   => $this->redactSecrets($trace),
                'source'  => $source,
            ]);
        } catch (\Throwable $e) {
            $this->log("Error al guardar error en DB: " . $e->getMessage(), 'error');
        }
    }

    public function getPreviousDecisions(string $context, int $limit = 5): array
    {
        $this->lazyLoadDb();
        if (!$this->db) return [];

        try {
            $sql = "SELECT * FROM jah_decisions WHERE context = :context ORDER BY id DESC LIMIT :limit";
            return $this->db->fetchAll($sql, [
                'context' => $context,
                'limit'   => $limit
            ]);
        } catch (\Throwable $e) {
            $this->log("Error al recuperar decisiones de DB: " . $e->getMessage(), 'error');
            return [];
        }
    }

    public function searchMemory(string $query, array $tiers = ['hot', 'warm'], int $limit = 10): array
    {
        if (!$this->tieredMemory) {
            $this->lazyLoadTieredMemory();
        }

        if (!$this->tieredMemory) {
            return [];
        }

        return $this->tieredMemory->search($query, $tiers, $limit);
    }

    public function storeInMemory(string $tier, string $key, array $data): bool
    {
        if (!$this->tieredMemory) {
            $this->lazyLoadTieredMemory();
        }

        if (!$this->tieredMemory) {
            return false;
        }

        return $this->tieredMemory->store($tier, $key, $data);
    }

    public function getFromMemory(string $tier, string $key): ?array
    {
        if (!$this->tieredMemory) {
            $this->lazyLoadTieredMemory();
        }

        if (!$this->tieredMemory) {
            return null;
        }

        return $this->tieredMemory->retrieve($tier, $key);
    }

    private function lazyLoadDb(): void
    {
        if ($this->db === null) {
            try {
                $this->db = Database::getInstance();
            } catch (\Throwable $e) {
                $this->log("No se pudo conectar a la base de datos: " . $e->getMessage(), 'warning');
            }
        }
    }

    private function lazyLoadTieredMemory(): void
    {
        if ($this->tieredMemory === null) {
            try {
                $config = $this->engine->getConfig('paths', []);
                $storagePath = $config['datacore_storage'] ?? dirname(__DIR__, 2) . '/runtime/memory/datacore';
                $hotStoragePath = $config['hot_storage'] ?? dirname(__DIR__, 2) . '/runtime/memory/pyramid';
                $this->tieredMemory = new TieredMemory($storagePath, $hotStoragePath);
            } catch (\Throwable $e) {
                $this->log("No se pudo cargar tiered memory: " . $e->getMessage(), 'warning');
            }
        }
    }

    private function redactSecrets(string $trace): string
    {
        return preg_replace('/(password|token|secret|api[_-]?key)=([^&\s]+)/i', '$1=[redacted]', $trace) ?? $trace;
    }
}
