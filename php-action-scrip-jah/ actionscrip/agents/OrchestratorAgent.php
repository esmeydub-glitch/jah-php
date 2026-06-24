<?php

declare(strict_types=1);

namespace Jah\Agents;

/**
 * OrchestratorAgent — FASE 5: Orchestrator Agent.
 * Recibe la estrategia del Predictor y se encarga de fraccionar las tareas,
 * balancear la carga y ordenar la creación/asignación de Workers.
 */
class OrchestratorAgent extends BaseAgent
{
    /** @var array Tareas activas en seguimiento */
    private array $activeTasks = [];

    protected function onBoot(): void
    {
        $this->subscribeToEvent('system.boot');
        $this->subscribeToEvent('predictor.strategy_decided');
        $this->subscribeToEvent('worker.completed');
        $this->subscribeToEvent('worker.failed');
    }

    public function handle(array $event): void
    {
        if ($event['type'] === 'system.boot') {
            $this->log("Orchestrator Agent listo para delegar trabajo.", 'info');
            return;
        }

        if ($event['type'] === 'predictor.strategy_decided') {
            $this->status = 'running';
            
            $action    = $event['payload']['action'] ?? 'unknown';
            $data      = $event['payload']['data'] ?? [];
            $strategy  = $event['payload']['strategy'] ?? [];
            $workers = max(1, min((int) ($strategy['workers_count'] ?? 1), $this->maxWorkers()));
            
            $this->log("Orquestando tareas para: {$action}. Dividiendo en {$workers} sub-tareas.", 'info');

            // Dividir el trabajo en lotes/sub-tareas
            $subTasks = $this->splitWorkIntoTasks($action, $data, $workers);

            $jobId = uniqid('job_', true);
            $this->activeTasks[$jobId] = [
                'action'        => $action,
                'total_tasks'   => count($subTasks),
                'pending_tasks' => count($subTasks),
                'completed'     => 0,
                'failed'        => 0,
                'results'       => [],
            ];

            // Despachar cada sub-tarea
            foreach ($subTasks as $index => $taskData) {
                $this->log("Despachando sub-tarea #{$index} para el trabajo {$jobId}", 'debug');
                
                $this->publish('orchestrator.task_assigned', [
                    'job_id'      => $jobId,
                    'task_index'  => $index,
                    'task_type'   => $this->getTaskTypeForAction($action),
                    'priority'    => $strategy['priority'] ?? 'medium',
                    'delay_ms'    => $strategy['delay_ms'] ?? 0,
                    'payload'     => $taskData,
                ]);
            }

            $this->status = 'idle';
        }

        if ($event['type'] === 'worker.completed') {
            $this->handleWorkerResolution($event['payload'], true);
        }

        if ($event['type'] === 'worker.failed') {
            $this->handleWorkerResolution($event['payload'], false);
        }
    }

    /**
     * Divide la carga de trabajo en $numChunks partes según el tipo de acción.
     */
    private function splitWorkIntoTasks(string $action, array $data, int $numChunks): array
    {
        // Si no hay datos estructurados para dividir, creamos tareas simples
        if (empty($data) || !is_array($data)) {
            $tasks = [];
            for ($i = 0; $i < $numChunks; $i++) {
                $tasks[] = ['action' => $action, 'chunk_id' => $i, 'params' => $data];
            }
            return $tasks;
        }

        // Si es un array divisible (ej. lista de IDs), lo segmentamos
        if (array_is_list($data) && count($data) > 1) {
            $chunks = array_chunk($data, max(1, (int) ceil(count($data) / $numChunks)));
            $tasks = [];
            foreach ($chunks as $index => $chunk) {
                $tasks[] = ['action' => $action, 'chunk_id' => $index, 'items' => $chunk];
            }
            return $tasks;
        }

        // Fallback: una sola tarea con todo el payload
        return [['action' => $action, 'chunk_id' => 0, 'params' => $data]];
    }

    /**
     * Retorna el tipo de worker necesario según la acción.
     */
    private function getTaskTypeForAction(string $action): string
    {
        return match ($action) {
            'download', 'api_fetch' => 'network',
            'write_file', 'import'  => 'file',
            'analyze', 'report'     => 'analysis',
            'test'                  => 'test',
            default                 => 'code',
        };
    }

    private function maxWorkers(): int
    {
        if (!$this->engine) {
            return 1;
        }

        $agents = $this->engine->getConfig('agents');
        return max(1, (int) ($agents['max_workers'] ?? 1));
    }

    /**
     * Monitorea la resolución de cada worker asignado y consolida los resultados del Job.
     */
    private function handleWorkerResolution(array $payload, bool $success): void
    {
        $jobId = $payload['job_id'] ?? null;
        if (!$jobId || !isset($this->activeTasks[$jobId])) {
            return;
        }

        $job = &$this->activeTasks[$jobId];
        $job['pending_tasks']--;
        
        if ($success) {
            $job['completed']++;
            $job['results'][] = $payload['result'] ?? null;
        } else {
            $job['failed']++;
        }

        $this->log(sprintf(
            "Progreso de Job %s: Completados: %d/%d (Pendientes: %d, Fallidos: %d)",
            $jobId,
            $job['completed'],
            $job['total_tasks'],
            $job['pending_tasks'],
            $job['failed']
        ), 'debug');

        // Si ya terminaron todos los sub-workers, reportar resultado final del Job
        if ($job['pending_tasks'] <= 0) {
            $this->log("¡Job {$jobId} finalizado completamente!", 'info');
            
            $this->publish('orchestrator.job_finished', [
                'job_id'      => $jobId,
                'action'      => $job['action'],
                'success'     => ($job['failed'] === 0),
                'total'       => $job['total_tasks'],
                'completed'   => $job['completed'],
                'failed'      => $job['failed'],
                'results'     => $job['results'],
            ]);

            unset($this->activeTasks[$jobId]);
        }
    }
}
