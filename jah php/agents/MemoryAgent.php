<?php

declare(strict_types=1);

namespace Jah\Agents;

use Jah\Memory\Database;

/**
 * MemoryAgent — FASE 2: Memoria MySQL JAH.
 * Guarda y recupera eventos, decisiones, errores y estados de agentes para proveer contexto al sistema.
 */
class MemoryAgent extends BaseAgent
{
    private ?Database $db = null;

    protected function onBoot(): void
    {
        // El MemoryAgent escucha eventos de logs, errores y decisiones para guardarlos persistente
        $this->subscribeToEvent('system.boot');
        $this->subscribeToEvent('gateway.validated');
        $this->subscribeToEvent('executor.started');
        $this->subscribeToEvent('executor.finished');
        $this->subscribeToEvent('analyst.report');
        $this->subscribeToEvent('system.error');
    }

    /**
     * Procesa y persiste eventos importantes en la base de datos MySQL.
     */
    public function handle(array $event): void
    {
        $this->lazyLoadDb();

        if ($event['type'] === 'system.boot') {
            if ($this->db) {
                $this->log('Memory Agent conectado a base de datos.', 'info');
                $this->saveEvent($event);
            } else {
                $this->log('Memory Agent sin base de datos. Persistencia desactivada.', 'warning');
            }
            return;
        }

        if (!$this->db) {
            $this->log('Base de datos no disponible. Ignorando guardado de evento: ' . $event['type'], 'warning');
            return;
        }

        $this->saveEvent($event);

        if ($event['type'] === 'system.error') {
            $this->saveError(
                $event['payload']['message'] ?? 'Unknown error',
                $event['payload']['trace'] ?? '',
                $event['source']
            );
        }
    }

    /**
     * Guarda un evento genérico en el log de eventos de MySQL.
     */
    public function saveEvent(array $event): void
    {
        if (!$this->db) {
            $this->log("Base de datos no disponible. Ignorando guardado de evento: " . $event['type'], 'warning');
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

    /**
     * Guarda un error detectado en el sistema.
     */
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

    /**
     * Recupera el historial de decisiones anteriores para evitar repetir errores.
     */
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

    /**
     * Carga de forma perezosa la instancia de base de datos para no fallar
     * si se inicializa el motor sin base de datos configurada.
     */
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

    private function redactSecrets(string $trace): string
    {
        return preg_replace('/(password|token|secret|api[_-]?key)=([^&\s]+)/i', '$1=[redacted]', $trace) ?? $trace;
    }
}
