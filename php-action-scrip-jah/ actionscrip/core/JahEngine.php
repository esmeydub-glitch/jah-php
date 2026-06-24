<?php

declare(strict_types=1);

namespace Jah\Core;

use Jah\Agents\BaseAgent;
use Jah\Agents\SalkAgent;

/**
 * JahEngine — El núcleo y orquestador central del Motor PHP JAH.
 * Coordina la inicialización de agentes, el bus de eventos y el enrutamiento.
 */
class JahEngine
{
    private static ?JahEngine $instance = null;
    
    private array $config = [];
    private EventBus $eventBus;
    private EventRouter $eventRouter;
    
    /** @var array<string, BaseAgent> Instancias de agentes activos */
    private array $agents = [];
    private bool $booted = false;
    private string $engineId;
    private string $bootId = '';

    private function __construct()
    {
        $this->eventBus = new EventBus();
        $this->eventRouter = new EventRouter();
        $this->engineId = 'engine_' . bin2hex(random_bytes(8));
    }

    public static function getInstance(): JahEngine
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inicializa el motor con su configuración global.
     */
    public function boot(array $config): void
    {
        if ($this->booted) {
            return;
        }

        $this->config = $config;
        $this->bootId = 'boot_' . bin2hex(random_bytes(12));
        
        // Registrar zona horaria
        if (isset($this->config['timezone'])) {
            date_default_timezone_set($this->config['timezone']);
        }

        // Crear directorios de logs y tmp si no existen
        $this->ensureDirectories();

        // Registrar oyente global en el EventBus que reenvía eventos al EventRouter
        $this->eventBus->subscribe('*', function (array $event) {
            $this->eventRouter->dispatch($event);
        });

        $this->booted = true;
        
        $this->log("Motor JAH inicializado correctamente (v{$this->config['version']})", 'info');

        // Levantar agentes configurados para arrancar al inicio
        $this->bootDefaultAgents();
        
        $this->eventBus->publish('system.boot', ['timestamp' => time()]);
    }

    /**
     * Registra e inicializa un agente.
     */
    public function registerAgent(string $name, BaseAgent $agent): void
    {
        $this->agents[$name] = $agent;
        $identity = [];

        if (!$agent instanceof SalkAgent) {
            $salk = $this->getSalkAgent();
            if ($salk instanceof SalkAgent) {
                $identity = $salk->birthAgent(
                    $agent->getId(),
                    $agent::class,
                    $this->engineId,
                    $this->bootId,
                    [$name]
                );
            }
        }

        $agent->boot($this, $identity);

        if ($identity !== []) {
            $validation = $this->getSalkAgent()?->validateAgentIdentity(
                $identity,
                $agent->getId(),
                $agent::class,
                $this->engineId,
                $this->bootId
            );

            if (!($validation['ok'] ?? false)) {
                unset($this->agents[$name]);
                throw new \RuntimeException("SALK rechazo el nacimiento del agente {$name}.");
            }

            $this->eventBus->publish('salk.agent_born', [
                'agent' => $name,
                'agent_id' => $agent->getId(),
                'boot_id' => $this->bootId,
                'time' => time(),
            ]);
        }

        $this->log("Agente registrado e inicializado: {$name}", 'debug');
    }

    /**
     * Obtiene un agente registrado por su nombre.
     */
    public function getAgent(string $name): ?BaseAgent
    {
        return $this->agents[$name] ?? null;
    }

    /**
     * Desconecta y limpia todos los recursos del motor.
     */
    public function shutdown(): void
    {
        if (!$this->booted) {
            return;
        }

        $this->eventBus->publish('system.shutdown', ['timestamp' => time()]);

        foreach ($this->agents as $name => $agent) {
            try {
                $agent->shutdown();
                $this->log("Agente detenido: {$name}", 'debug');
            } catch (\Throwable $e) {
                $this->log("Error al apagar agente {$name}: " . $e->getMessage(), 'error');
            }
        }

        $this->agents = [];
        $this->booted = false;
        $this->log("Motor JAH detenido.", 'info');
    }

    public function getEventBus(): EventBus
    {
        return $this->eventBus;
    }

    public function getEventRouter(): EventRouter
    {
        return $this->eventRouter;
    }

    public function dispatchSignedEvent(array $event): array
    {
        $this->eventBus->publish('salk.event_received', [
            'event' => $event['event'] ?? null,
            'component_id' => $event['component_id'] ?? null,
            'time' => time(),
        ]);

        $salk = $this->getSalkAgent();
        if (!$salk instanceof SalkAgent) {
            return ['ok' => false, 'error' => 'SALK_AGENT_NOT_AVAILABLE'];
        }

        $validation = $salk->validateEvent($event);
        if (!($validation['ok'] ?? false)) {
            $this->eventBus->publish('salk.event_rejected', [
                'reason' => $validation['error'] ?? 'SALK_REJECTED',
                'event' => $event['event'] ?? null,
                'component_id' => $event['component_id'] ?? null,
                'time' => time(),
            ]);

            return [
                'ok' => false,
                'error' => $validation['error'] ?? 'SALK_REJECTED',
            ];
        }

        $this->eventBus->publish('salk.event_accepted', [
            'event' => $event['event'] ?? null,
            'component_id' => $event['component_id'] ?? null,
            'salk' => $validation['payload'],
            'time' => time(),
        ]);

        $this->eventBus->publish((string) $event['event'], [
            'event' => $event,
            'salk' => $validation['payload'],
        ]);

        return [
            'ok' => true,
            'event' => $event['event'] ?? null,
            'salk' => $validation['payload'],
        ];
    }

    public function getEngineId(): string
    {
        return $this->engineId;
    }

    public function getBootId(): string
    {
        return $this->bootId;
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Escribe un mensaje de log en el destino configurado.
     */
    public function log(string $message, string $level = 'info'): void
    {
        if (!($this->config['log']['enabled'] ?? false)) {
            return;
        }

        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        $configLevel = $this->config['log']['level'] ?? 'info';
        
        if (($levels[$level] ?? 1) < ($levels[$configLevel] ?? 1)) {
            return;
        }

        $logFile = $this->config['log']['file'] ?? dirname(__DIR__) . '/logs/jah.log';
        $formatted = sprintf(
            "[%s] [%s] %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message
        );

        file_put_contents($logFile, $formatted, FILE_APPEND);
    }

    private function bootDefaultAgents(): void
    {
        $bootList = $this->config['agents']['boot_on_start'] ?? [];
        foreach ($bootList as $agentClass) {
            $fullClass = "\\Jah\\Agents\\" . $agentClass;
            if (class_exists($fullClass)) {
                $agent = new $fullClass();
                $this->registerAgent($agentClass, $agent);
            } else {
                $this->log("No se pudo pre-cargar el agente: {$fullClass} (Clase no encontrada)", 'warning');
            }
        }
    }

    private function getSalkAgent(): ?SalkAgent
    {
        $agent = $this->agents['SalkAgent'] ?? null;
        return $agent instanceof SalkAgent ? $agent : null;
    }

    private function ensureDirectories(): void
    {
        $paths = $this->config['paths'] ?? [];
        foreach (['logs', 'tmp', 'cache'] as $key) {
            if (isset($paths[$key]) && !is_dir($paths[$key])) {
                mkdir($paths[$key], 0775, true);
            }
        }
    }
}
