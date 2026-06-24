<?php

declare(strict_types=1);

namespace Jah\Agents;

/**
 * WorkerAgent — FASE 7: Worker Agent.
 * Nace dinámicamente, toma una tarea específica asignada por el Orchestrator,
 * la ejecuta, entrega los resultados y finaliza (simulado o CLI).
 */
class WorkerAgent extends BaseAgent
{
    private string $jobId = '';
    private int $taskIndex = 0;
    private string $taskType = 'code';

    protected function onBoot(): void
    {
        // En un esquema distribuido, este agente sería instanciado por proceso
        // En este scaffold se suscribe a tareas asignadas
        $this->subscribeToEvent('orchestrator.task_assigned');
    }

    public function handle(array $event): void
    {
        if ($event['type'] === 'orchestrator.task_assigned') {
            $payload = $event['payload'] ?? [];
            
            $this->jobId     = (string) ($payload['job_id'] ?? '');
            $this->taskIndex = max(0, (int) ($payload['task_index'] ?? 0));
            $this->taskType  = (string) ($payload['task_type'] ?? 'code');
            
            $this->status = 'running';
            $this->log("Iniciando tarea #{$this->taskIndex} (tipo: {$this->taskType}) del Job {$this->jobId}", 'debug');

            // Emitir evento de inicio de ejecución del worker
            $this->publish('worker.started', [
                'worker_id'  => $this->id,
                'job_id'     => $this->jobId,
                'task_index' => $this->taskIndex,
            ]);

            try {
                // Simular retraso configurado
                if (!empty($payload['delay_ms'])) {
                    usleep(min((int) $payload['delay_ms'], 5_000) * 1000);
                }

                $result = $this->executeTask($payload['payload'] ?? []);
                
                $this->log("Tarea #{$this->taskIndex} completada con éxito.", 'debug');
                
                $this->publish('worker.completed', [
                    'worker_id'  => $this->id,
                    'job_id'     => $this->jobId,
                    'task_index' => $this->taskIndex,
                    'result'     => $result,
                ]);

            } catch (\Throwable $e) {
                $this->log("Error en tarea #{$this->taskIndex}: " . $e->getMessage(), 'error');
                
                $this->publish('worker.failed', [
                    'worker_id'  => $this->id,
                    'job_id'     => $this->jobId,
                    'task_index' => $this->taskIndex,
                    'error'      => $e->getMessage(),
                ]);
            }

            // Simular muerte del worker
            $this->status = 'dead';
            $this->log("Worker destruido.", 'debug');
        }
    }

    /**
     * Ejecuta la lógica correspondiente al tipo de worker.
     */
    private function executeTask(array $data): array
    {
        return match ($this->taskType) {
            'network'  => $this->executeNetworkTask($data),
            'file'     => $this->executeFileTask($data),
            'analysis' => $this->executeAnalysisTask($data),
            'test'     => $this->executeTestTask($data),
            default    => $this->executeCodeTask($data),
        };
    }

    private function executeCodeTask(array $data): array
    {
        // Ejecución de código php genérico / cálculo básico
        $param = $data['params'] ?? [];
        return [
            'status' => 'success',
            'output' => 'Código ejecutado de forma nativa.',
            'echo'   => $param
        ];
    }

    private function executeNetworkTask(array $data): array
    {
        // Enlaza indirectamente con el NetworkAgent publicando peticiones
        $this->log("Worker solicitando recursos de red al NetworkAgent...", 'debug');
        return [
            'status' => 'success',
            'details'=> 'Datos de red enrutados al NetworkAgent'
        ];
    }

    private function executeFileTask(array $data): array
    {
        // Operaciones de lectura/escritura de archivos
        $action = $data['action'] ?? 'read';
        $this->log("Ejecutando operación de archivo: {$action}", 'debug');
        return [
            'status' => 'success',
            'file_processed' => true,
        ];
    }

    private function executeAnalysisTask(array $data): array
    {
        // Análisis de datos
        return [
            'status' => 'success',
            'metrics_evaluated' => count($data),
            'result_score' => 100
        ];
    }

    private function executeTestTask(array $data): array
    {
        // Lógica de pruebas de integración / unitarias internas
        return [
            'status' => 'success',
            'tests_passed' => true
        ];
    }
}
