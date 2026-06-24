<?php

declare(strict_types=1);

namespace Jah\Agents;

/**
 * AnalystAgent — FASE 10: Analyst Agent.
 * Compara los resultados esperados con los reales, mide el rendimiento
 * y envía métricas de optimización y retroalimentación al Predictor.
 */
class AnalystAgent extends BaseAgent
{
    protected function onBoot(): void
    {
        $this->subscribeToEvent('system.boot');
        $this->subscribeToEvent('orchestrator.job_finished');
    }

    public function handle(array $event): void
    {
        if ($event['type'] === 'system.boot') {
            $this->log("Analyst Agent listo para auditar el rendimiento.", 'info');
            return;
        }

        if ($event['type'] === 'orchestrator.job_finished') {
            $this->status = 'running';
            $payload = $event['payload'] ?? [];
            
            $jobId     = $payload['job_id'] ?? 'unknown';
            $action    = $payload['action'] ?? 'unknown';
            $success   = $payload['success'] ?? false;
            $total     = $payload['total'] ?? 0;
            $completed = $payload['completed'] ?? 0;
            $failed    = $payload['failed'] ?? 0;

            $this->log("Analizando rendimiento del Job: {$jobId} (acción: {$action})", 'info');

            // Calcular tasa de éxito
            $successRate = $total > 0 ? ($completed / $total) * 100 : 0.0;

            // Medir impacto y sugerencias
            $statusReport = $success ? 'optimal' : 'degraded';
            if ($failed > 0) {
                $this->log("Se detectaron {$failed} tareas fallidas en el Job {$jobId}. Sugiriendo revisión.", 'warning');
            }

            $report = [
                'job_id'       => $jobId,
                'action'       => $action,
                'total_tasks'  => $total,
                'success_rate' => $successRate,
                'failed_count' => $failed,
                'status'       => $statusReport,
                'timestamp'    => microtime(true),
            ];

            // Enviar reporte al bus de eventos para que Predictor y Optimizer lo registren
            $this->publish('analyst.report', $report);

            $this->status = 'idle';
        }
    }
}
