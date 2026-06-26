<?php

declare(strict_types=1);

namespace Jah\Agents;

/**
 * OptimizerAgent — FASE 11: Optimizer Agent.
 * Analiza el historial de rendimiento de los Analyst reports y las métricas
 * para formular recomendaciones de optimización (rutas rápidas, cuellos de botella).
 */
class OptimizerAgent extends BaseAgent
{
    private array $historyReports = [];

    protected function onBoot(): void
    {
        $this->subscribeToEvent('system.boot');
        $this->subscribeToEvent('analyst.report');
    }

    public function handle(array $event): void
    {
        if ($event['type'] === 'system.boot') {
            $this->log("Optimizer Agent analizando patrones del sistema.", 'info');
            return;
        }

        if ($event['type'] === 'analyst.report') {
            $this->status = 'running';
            $report = $event['payload'];
            
            // Registrar reporte en memoria local
            $this->historyReports[] = $report;
            
            // Mantener solo los últimos 50 reportes en memoria
            if (count($this->historyReports) > 50) {
                array_shift($this->historyReports);
            }

            // Evaluar si una acción está fallando repetidamente
            $this->evaluatePerformancePatterns();

            $this->status = 'idle';
        }
    }

    /**
     * Evalúa el rendimiento reciente para detectar cuellos de botella o fallos repetitivos.
     */
    private function evaluatePerformancePatterns(): void
    {
        $failuresByAction = [];

        foreach ($this->historyReports as $report) {
            $action = $report['action'] ?? 'unknown';
            if (!isset($failuresByAction[$action])) {
                $failuresByAction[$action] = [
                    'total' => 0,
                    'failed' => 0
                ];
            }

            $failuresByAction[$action]['total']++;
            if ($report['failed_count'] > 0) {
                $failuresByAction[$action]['failed']++;
            }
        }

        // Generar recomendaciones si la tasa de fallo de una acción supera el 30%
        foreach ($failuresByAction as $action => $stats) {
            if ($stats['total'] >= 3) {
                $failPct = ($stats['failed'] / $stats['total']) * 100;
                if ($failPct > 30) {
                    $this->log("¡RECOMENDACIÓN! La acción '{$action}' tiene un porcentaje de error del " . round($failPct, 2) . "% en los últimos ejecuciones. Se sugiere reducir cantidad de workers o auditar configuración.", 'warning');
                    
                    $this->publish('optimizer.recommendation', [
                        'target_action'  => $action,
                        'reason'         => "High failure rate detected: " . round($failPct, 2) . "%",
                        'recommendation' => 'reduce_workers_limit_or_cache_responses',
                        'timestamp'      => time(),
                    ]);
                }
            }
        }
    }
}
