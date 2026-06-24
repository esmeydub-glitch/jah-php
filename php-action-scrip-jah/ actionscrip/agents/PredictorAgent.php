<?php

declare(strict_types=1);

namespace Jah\Agents;

/**
 * PredictorAgent — FASE 4: Predictor Agent.
 * Analiza el contexto de carga y el historial previo para definir la estrategia de ejecución óptima.
 */
class PredictorAgent extends BaseAgent
{
    private array $systemMetrics = [];

    protected function onBoot(): void
    {
        $this->subscribeToEvent('system.boot');
        $this->subscribeToEvent('observer.metrics_collected');
        $this->subscribeToEvent('gateway.validated');
    }

    public function handle(array $event): void
    {
        if ($event['type'] === 'system.boot') {
            $this->log("Predictor Agent inicializado y listo para pronosticar.", 'info');
            return;
        }

        if ($event['type'] === 'observer.metrics_collected') {
            $this->systemMetrics = $event['payload'];
            return;
        }

        if ($event['type'] === 'gateway.validated') {
            $this->status = 'running';
            $action = $event['payload']['action'] ?? 'unknown';
            $this->log("Evaluando recursos y decidiendo estrategia para la acción: {$action}", 'info');

            // Calcular recursos y cantidad de workers requeridos
            $strategy = $this->calculateStrategy($action, $event['payload']);

            $this->log(sprintf(
                "Estrategia decidida: Workers recomendados: %d | Prioridad: %s | Delay: %d ms",
                $strategy['workers_count'],
                $strategy['priority'],
                $strategy['delay_ms']
            ), 'info');

            // Emitir la estrategia calculada para que la tome el Orchestrator
            $this->publish('predictor.strategy_decided', [
                'action'       => $action,
                'data'         => $event['payload']['data'] ?? [],
                'strategy'     => $strategy,
                'original_evt_id' => $event['id'],
            ]);

            $this->status = 'idle';
        }
    }

    /**
     * Lógica predictiva simulada basada en carga actual e histórica.
     */
    private function calculateStrategy(string $action, array $payload): array
    {
        $cpuLoad = $this->systemMetrics['cpu_load'][0] ?? 0.5;
        $ramFree = $this->systemMetrics['memory_free'] ?? 1024 * 1024; // en KB

        // Cantidad de workers por defecto
        $workersCount = 1;
        $priority = 'medium';
        $delayMs = 0;

        // Regla de predicción básica: Si es una acción conocida y pesada
        if (in_array($action, ['backup', 'sync_all', 'batch_process'])) {
            $workersCount = 3;
            $priority = 'high';
        }

        // Si la CPU está muy cargada, posponer o degradar prioridad
        if ($cpuLoad > 3.0) {
            $priority = 'low';
            $workersCount = max(1, $workersCount - 1);
            $delayMs = 500; // Agregar un retraso para bajar presión
            $this->log("Carga alta de CPU ({$cpuLoad}). Degradando prioridad y añadiendo delay.", 'warning');
        }

        // Si la RAM está muy baja, forzar ejecución secuencial
        if ($ramFree < 512 * 1024) { // Menos de 512MB
            $workersCount = 1;
            $this->log("RAM baja. Forzando a 1 solo worker.", 'warning');
        }

        return [
            'workers_count' => $workersCount,
            'priority'      => $priority,
            'delay_ms'      => $delayMs,
            'timestamp'     => time(),
        ];
    }
}
