<?php

declare(strict_types=1);

namespace Jah\Agents;

use Jah\Network\HttpClient;

/**
 * NetworkAgent — FASE 6: Network Agent.
 * Maneja llamadas HTTP, llamadas a APIs de terceros, consumo de Webhooks y sockets de comunicación.
 */
class NetworkAgent extends BaseAgent
{
    private ?HttpClient $client = null;

    protected function onBoot(): void
    {
        $this->subscribeToEvent('system.boot');
        $this->subscribeToEvent('network.request');
    }

    public function handle(array $event): void
    {
        if ($event['type'] === 'system.boot') {
            $this->client = new HttpClient();
            $this->log("Network Agent listo para operaciones de red.", 'info');
            return;
        }

        if ($event['type'] === 'network.request') {
            $this->status = 'running';
            $payload = $event['payload'] ?? [];
            $url     = $payload['url'] ?? '';
            $method  = strtoupper($payload['method'] ?? 'GET');
            $data    = $payload['data'] ?? [];
            $headers = $payload['headers'] ?? [];

            if (empty($url) || !$this->isAllowedUrl($url)) {
                $this->log('Llamada de red fallida: URL inválida.', 'warning');
                $this->publish('network.error', [
                    'error' => 'Invalid URL',
                    'request_payload' => $payload,
                ]);
                $this->status = 'idle';
                return;
            }

            $this->log("Realizando petición {$method} a: {$url}", 'info');

            try {
                if ($this->client === null) {
                    $this->client = new HttpClient();
                }

                $response = $this->client->request($method, $url, $data, $headers);

                $this->log("Petición completada con código HTTP: " . $response['code'], 'info');
                
                $this->publish('network.response', [
                    'url'        => $url,
                    'code'       => $response['code'],
                    'body'       => $response['body'],
                    'headers'    => $response['headers'],
                    'request_id' => $payload['request_id'] ?? null,
                ]);

            } catch (\Throwable $e) {
                $this->log("Fallo en la petición de red: " . $e->getMessage(), 'error');
                
                $this->publish('network.failed', [
                    'url'        => $url,
                    'error'      => $e->getMessage(),
                    'request_id' => $payload['request_id'] ?? null,
                ]);
            }

            $this->status = 'idle';
        }
    }

    private function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);
        return in_array($scheme, ['http', 'https'], true);
    }
}
