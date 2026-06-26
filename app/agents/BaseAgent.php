<?php

declare(strict_types=1);

namespace Jah\Agents;

use Jah\Core\JahEngine;

/**
 * BaseAgent — Clase base abstracta para todos los agentes del motor JAH.
 */
abstract class BaseAgent
{
    protected string $id;
    protected ?JahEngine $engine = null;
    protected string $status = 'offline';

    public function __construct()
    {
        $this->id = uniqid(strtolower((new \ReflectionClass($this))->getShortName()) . '_', true);
    }

    /**
     * Inicializa el agente con el motor.
     */
    public function boot(JahEngine $engine): void
    {
        $this->engine = $engine;
        $this->status = 'idle';
        $this->onBoot();
    }

    /**
     * Ciclo de vida: Método para que los agentes ejecuten su lógica específica de inicialización
     * (por ejemplo, suscribirse a eventos).
     */
    abstract protected function onBoot(): void;

    /**
     * Maneja un evento enrutado a este agente.
     *
     * @param array $event Datos completos del evento
     */
    abstract public function handle(array $event): void;

    /**
     * Ciclo de vida: Detiene el agente y limpia sus recursos.
     */
    public function shutdown(): void
    {
        $this->status = 'offline';
        $this->onShutdown();
    }

    /**
     * Ciclo de vida: Método opcional para limpieza específica en agentes hijos.
     */
    protected function onShutdown(): void
    {
        // Opcional en el hijo
    }

    /**
     * Helper: Publicar un evento en el bus global.
     */
    protected function publish(string $type, array $payload = []): void
    {
        if ($this->engine) {
            $this->engine->getEventBus()->publish($type, $payload, $this->id);
        }
    }

    /**
     * Helper: Registrar suscripción en el enrutador para este agente.
     */
    protected function subscribeToEvent(string $pattern): void
    {
        if ($this->engine) {
            $this->engine->getEventRouter()->route($pattern, $this);
        }
    }

    /**
     * Helper: Escribir log en el motor.
     */
    protected function log(string $message, string $level = 'info'): void
    {
        if ($this->engine) {
            $this->engine->log("[{$this->id}] {$message}", $level);
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
}
