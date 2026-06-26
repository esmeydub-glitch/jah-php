<?php

declare(strict_types=1);

namespace Jah\Agents;

use Jah\Cache\CacheManager;

/**
 * CacheAgent — FASE 8: Cache Agent.
 * Almacena y recupera respuestas rápidas, estados rápidos y cómputos temporales
 * para evitar sobrecargar la base de datos o recalcular tareas pesadas.
 */
class CacheAgent extends BaseAgent
{
    private ?CacheManager $manager = null;

    protected function onBoot(): void
    {
        $this->subscribeToEvent('system.boot');
        $this->subscribeToEvent('cache.get');
        $this->subscribeToEvent('cache.set');
        $this->subscribeToEvent('cache.delete');
    }

    public function handle(array $event): void
    {
        if ($event['type'] === 'system.boot') {
            $configPaths = $this->engine ? $this->engine->getConfig('paths') : [];
            $cachePath = $configPaths['cache'] ?? dirname(__DIR__, 2) . '/cache/store';
            $this->manager = new CacheManager($cachePath);
            $this->log("Cache Agent inicializado en la ruta: {$cachePath}", 'info');
            return;
        }

        if ($this->manager === null) {
            $this->log("Administrador de caché no disponible.", 'warning');
            return;
        }

        $payload = $event['payload'] ?? [];

        switch ($event['type']) {
            case 'cache.get':
                $key = (string) ($payload['key'] ?? '');
                if ($key === '' || strlen($key) > 160) {
                    $this->publish('cache.miss', ['request_id' => $payload['request_id'] ?? null]);
                    break;
                }

                $value = $this->manager->get($key);
                
                if ($value !== null) {
                    $this->log("Cache HIT para la llave: {$key}", 'debug');
                    $this->publish('cache.hit', [
                        'key'   => $key,
                        'value' => $value,
                        'request_id' => $payload['request_id'] ?? null,
                    ]);
                } else {
                    $this->log("Cache MISS para la llave: {$key}", 'debug');
                    $this->publish('cache.miss', [
                        'key'   => $key,
                        'request_id' => $payload['request_id'] ?? null,
                    ]);
                }
                break;

            case 'cache.set':
                $key = (string) ($payload['key'] ?? '');
                if ($key === '' || strlen($key) > 160) {
                    $this->log('Cache guardado rechazado: llave inválida.', 'warning');
                    break;
                }

                $value = $payload['value'] ?? null;
                $ttl = max(1, min((int) ($payload['ttl'] ?? 3600), $this->maxTtl()));
                $this->manager->set($key, $value, $ttl);
                $this->log("Cache guardado para la llave: {$key} (TTL: {$ttl}s)", 'debug');
                $this->publish('cache.saved', ['key' => $key]);
                break;

            case 'cache.delete':
                $key = (string) ($payload['key'] ?? '');
                if ($key !== '' && strlen($key) <= 160) {
                    $this->manager->delete($key);
                    $this->log("Cache borrado para la llave: {$key}", 'debug');
                    $this->publish('cache.deleted', ['key' => $key]);
                }
                break;
        }
    }

    private function maxTtl(): int
    {
        if (!$this->engine) {
            return 86_400;
        }

        $security = $this->engine->getConfig('security');
        return max(1, (int) ($security['max_cache_ttl'] ?? 86_400));
    }
}
