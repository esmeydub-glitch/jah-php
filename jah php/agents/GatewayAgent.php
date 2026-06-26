<?php

declare(strict_types=1);

namespace Jah\Agents;

/**
 * GatewayAgent — FASE 1: Núcleo PHP Gateway.
 * Recibe peticiones del exterior (HTTP, CLI, Sockets) y las inyecta como eventos en el motor.
 */
class GatewayAgent extends BaseAgent
{
    protected function onBoot(): void
    {
        // El Gateway escucha peticiones externas y también el inicio del sistema
        $this->subscribeToEvent('system.boot');
        $this->subscribeToEvent('gateway.inbound');
    }

    /**
     * Procesa peticiones entrantes.
     */
    public function handle(array $event): void
    {
        $this->log("Procesando evento del tipo: " . $event['type'], 'debug');

        if ($event['type'] === 'system.boot') {
            $this->log("Gateway listo para recibir tráfico.", 'info');
            return;
        }

        if ($event['type'] === 'gateway.inbound') {
            $payload = $event['payload'] ?? [];
            
            // Validar la estructura mínima del payload
            if (empty($payload['action']) || !$this->isValidAction((string) $payload['action'])) {
                $this->log("Evento entrante rechazado: Campo 'action' inválido", 'warning');
                $this->publish('gateway.rejected', [
                    'reason' => "Invalid 'action' field",
                    'original_event_id' => $event['id'],
                ]);
                return;
            }

            $action = (string) $payload['action'];
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            $this->status = 'running';
            $this->log("Petición validada correctamente: " . $action, 'info');

            // Emitir evento al motor de que hay una nueva petición de acción validada
            $this->publish('gateway.validated', [
                'action' => $action,
                'data'   => $data,
                'client_ip' => $payload['client_ip'] ?? '127.0.0.1',
                'timestamp' => microtime(true),
            ]);

            $this->status = 'idle';
        }
    }

    /**
     * Método de conveniencia para inyectar peticiones HTTP.
     */
    public function handleHttpRequest(array $requestData): void
    {
        $this->publish('gateway.inbound', [
            'action'    => $requestData['action'] ?? null,
            'data'      => $requestData['data'] ?? [],
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
        ]);
    }

    /**
     * Método de conveniencia para inyectar peticiones CLI.
     */
    public function handleCliRequest(array $args): void
    {
        $action = $args[1] ?? null;
        $data = [];
        
        // Parsear argumentos clave=valor (ej. name=jah age=1)
        for ($i = 2; $i < count($args); $i++) {
            if (str_contains($args[$i], '=')) {
                [$key, $val] = explode('=', $args[$i], 2);
                $data[$key] = $val;
            } else {
                $data[] = $args[$i];
            }
        }

        $this->publish('gateway.inbound', [
            'action' => $action,
            'data'   => $data,
            'source' => 'cli',
        ]);
    }

    private function isValidAction(string $action): bool
    {
        return strlen($action) <= 80 && preg_match('/^[A-Za-z0-9_.:-]+$/', $action) === 1;
    }
}
