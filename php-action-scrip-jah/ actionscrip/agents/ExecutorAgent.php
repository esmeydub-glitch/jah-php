<?php

declare(strict_types=1);

namespace Jah\Agents;

/**
 * ExecutorAgent — FASE 9: Executor Agent.
 * Ejecuta comandos fijos del sistema mediante proc_open sin shell.
 * Cuenta con una lista blanca de comandos para garantizar la seguridad.
 */
class ExecutorAgent extends BaseAgent
{
    /** @var array Lista de comandos permitidos (whitelist) */
    private array $allowedCommands = [
        ['php', '-v'],
        ['df', '-h'],
    ];

    protected function onBoot(): void
    {
        $this->subscribeToEvent('system.boot');
        $this->subscribeToEvent('executor.run_cmd');
    }

    public function handle(array $event): void
    {
        if ($event['type'] === 'system.boot') {
            $this->log("Executor Agent inicializado. Comandos vigilados.", 'info');
            return;
        }

        if ($event['type'] === 'executor.run_cmd') {
            $this->status = 'running';
            $payload = $event['payload'] ?? [];
            $command = $this->parseCommand($payload['command'] ?? '');
            if (!$command) {
                $this->log('Ejecución denegada: Comando inválido.', 'warning');
                $this->publish('executor.denied', [
                    'command' => (string) ($payload['command'] ?? ''),
                    'reason' => 'Invalid command',
                ]);
                $this->status = 'idle';
                return;
            }

            if (!$this->isCommandAllowed($command)) {
                $this->log("¡BLOQUEO DE SEGURIDAD! Intento de ejecutar comando no permitido: " . implode(' ', $command), 'warning');
                $this->publish('executor.denied', [
                    'command' => implode(' ', $command),
                    'reason' => 'Command not in whitelist',
                ]);
                $this->status = 'idle';
                return;
            }

            $this->log('Ejecutando comando: ' . implode(' ', $command), 'info');
            
            $this->publish('executor.started', [
                'command'    => implode(' ', $command),
                'started_at' => microtime(true)
            ]);

            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptorSpec, $pipes);

            if (is_resource($process)) {
                fclose($pipes[0]);

                $stdout = stream_get_contents($pipes[1]);
                fclose($pipes[1]);

                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[2]);

                $exitCode = proc_close($process);

                if ($exitCode === 0) {
                    $this->log("Comando ejecutado con éxito.", 'info');
                    $this->publish('executor.finished', [
                        'command'   => implode(' ', $command),
                        'exit_code' => $exitCode,
                        'output'    => trim((string) $stdout),
                    ]);
                } else {
                    $this->log("Comando finalizó con código de error {$exitCode}. Detalle: {$stderr}", 'warning');
                    $this->publish('executor.failed', [
                        'command'   => implode(' ', $command),
                        'exit_code' => $exitCode,
                        'error'     => trim((string) $stderr),
                    ]);
                }
            } else {
                $this->log("Error interno: No se pudo abrir el proceso del sistema.", 'error');
                $this->publish('executor.failed', [
                    'command' => $command,
                    'error'   => 'Could not open process resource.'
                ]);
            }

            $this->status = 'idle';
        }
    }

    private function parseCommand(mixed $command): ?array
    {
        if (!is_string($command) || trim($command) === '') {
            return null;
        }

        if (preg_match('/[;&|`$()<>]/', $command) === 1) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($command));
        if ($parts === false || count($parts) === 0 || count($parts) > 8) {
            return null;
        }

        return array_values($parts);
    }

    private function isCommandAllowed(array $command): bool
    {
        foreach ($this->allowedCommands as $allowed) {
            if ($command === $allowed) {
                return true;
            }
        }

        return false;
    }
}
