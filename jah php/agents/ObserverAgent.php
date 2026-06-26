<?php

declare(strict_types=1);

namespace Jah\Agents;

/**
 * ObserverAgent — FASE 3: Observer Agent.
 * Vigila el estado de salud del servidor (CPU, Memoria, Disco, Procesos) y levanta alertas si el sistema se satura.
 */
class ObserverAgent extends BaseAgent
{
    protected function onBoot(): void
    {
        $this->subscribeToEvent('system.boot');
        $this->subscribeToEvent('observer.check');
    }

    public function handle(array $event): void
    {
        if ($event['type'] === 'system.boot') {
            $this->log("Observer Agent monitoreando el sistema.", 'info');
            return;
        }

        if ($event['type'] === 'observer.check') {
            $metrics = $this->collectMetrics();
            $this->log(sprintf(
                "Métricas obtenidas: CPU Load: %s | RAM Libre: %s MB | Disco Libre: %s GB",
                implode(', ', $metrics['cpu_load']),
                round($metrics['memory_free'] / 1024),
                round($metrics['disk_free'] / (1024 * 1024 * 1024), 2)
            ), 'debug');

            // Emitir métricas recopiladas
            $this->publish('observer.metrics_collected', $metrics);

            // Evaluar umbrales de alerta
            $this->evaluateThresholds($metrics);
        }
    }

    /**
     * Recopila información de CPU, RAM, y Disco.
     */
    public function collectMetrics(): array
    {
        $cpu = sys_getloadavg();
        if ($cpu === false) {
            $cpu = [0.0, 0.0, 0.0];
        }

        $mem = $this->getMemoryUsage();
        
        $diskTotal = disk_total_space('/');
        $diskFree = disk_free_space('/');

        return [
            'cpu_load'      => $cpu,
            'memory_total'  => $mem['total'] ?? 0,
            'memory_free'   => $mem['free'] ?? 0,
            'memory_used'   => ($mem['total'] ?? 0) - ($mem['free'] ?? 0),
            'disk_total'    => $diskTotal ?: 0,
            'disk_free'     => $diskFree ?: 0,
            'timestamp'     => microtime(true),
        ];
    }

    /**
     * Evalúa si las métricas cruzan algún límite crítico y emite alertas.
     */
    private function evaluateThresholds(array $metrics): void
    {
        // Alerta de RAM (Menos del 10% de RAM libre)
        if ($metrics['memory_total'] > 0) {
            $ramPct = ($metrics['memory_free'] / $metrics['memory_total']) * 100;
            if ($ramPct < 10) {
                $this->log("¡PRESIÓN DE RAM DETECTADA! Solo queda el " . round($ramPct, 2) . "% libre.", 'warning');
                $this->publish('observer.alert.memory_low', ['free_pct' => $ramPct]);
            }
        }

        // Alerta de CPU (Carga del último minuto superior a la cantidad de cores, ej. 4.0)
        if ($metrics['cpu_load'][0] > 4.0) {
            $this->log("¡ALTA CARGA DE CPU DETECTADA!: " . $metrics['cpu_load'][0], 'warning');
            $this->publish('observer.alert.cpu_high', ['load' => $metrics['cpu_load'][0]]);
        }
    }

    /**
     * Lee /proc/meminfo si está en Linux, o estima de forma alternativa.
     */
    private function getMemoryUsage(): array
    {
        $mem = ['total' => 0, 'free' => 0];

        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/meminfo')) {
            $data = file_get_contents('/proc/meminfo');
            if ($data !== false) {
                preg_match('/MemTotal:\s+(\d+)/', $data, $matchesTotal);
                preg_match('/MemAvailable:\s+(\d+)/', $data, $matchesAvail);
                
                // Si no hay MemAvailable, estimamos con MemFree
                if (empty($matchesAvail)) {
                    preg_match('/MemFree:\s+(\d+)/', $data, $matchesAvail);
                }

                $mem['total'] = isset($matchesTotal[1]) ? (int) $matchesTotal[1] : 0;
                $mem['free']  = isset($matchesAvail[1]) ? (int) $matchesAvail[1] : 0;
            }
        } else {
            // Fallback aproximado para entornos no Linux
            $mem['total'] = 8 * 1024 * 1024; // Ficticio 8GB
            $mem['free']  = 4 * 1024 * 1024;  // Ficticio 4GB
        }

        return $mem;
    }
}
