<?php

declare(strict_types=1);

namespace Jah\Agents;

/**
 * CleanerAgent — FASE 12: Cleaner Agent.
 * Se encarga de limpiar archivos de caché expirados, matar subprocesos huérfanos,
 * liberar memoria temporal y limpiar archivos temporales viejos.
 */
class CleanerAgent extends BaseAgent
{
    protected function onBoot(): void
    {
        $this->subscribeToEvent('system.boot');
        $this->subscribeToEvent('cleaner.run');
    }

    public function handle(array $event): void
    {
        if ($event['type'] === 'system.boot') {
            $this->log("Cleaner Agent inicializado. Vigilando recursos basura.", 'info');
            return;
        }

        if ($event['type'] === 'cleaner.run') {
            $this->status = 'running';
            $this->log("Iniciando ciclo de limpieza del sistema...", 'info');

            $cleanedCache = $this->cleanExpiredCache();
            $cleanedTmp   = $this->cleanTempFolder();

            $this->log(sprintf(
                "Limpieza finalizada. Archivos de caché purgados: %d | Archivos temporales eliminados: %d",
                $cleanedCache,
                $cleanedTmp
            ), 'info');

            $this->publish('cleaner.finished', [
                'cleaned_cache_files' => $cleanedCache,
                'cleaned_tmp_files'   => $cleanedTmp,
                'timestamp'           => time()
            ]);

            $this->status = 'idle';
        }
    }

    /**
     * Limpia la carpeta de caché eliminando los archivos cuya fecha de expiración ya pasó.
     */
    private function cleanExpiredCache(): int
    {
        if (!$this->engine) return 0;
        $paths = $this->engine->getConfig('paths');
        $cachePath = $paths['cache'] ?? null;

        if (!$cachePath || !is_dir($cachePath)) {
            return 0;
        }

        $count = 0;
        $files = glob($cachePath . '/*.php');
        
        if ($files === false) return 0;

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) continue;

            $data = unserialize($content, ['allowed_classes' => false]);
            if (is_array($data) && isset($data['expire_at']) && time() > $data['expire_at']) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Purga archivos en la carpeta tmp/ que tengan más de 24 horas de antigüedad.
     */
    private function cleanTempFolder(): int
    {
        if (!$this->engine) return 0;
        $paths = $this->engine->getConfig('paths');
        $tmpPath = $paths['tmp'] ?? null;

        if (!$tmpPath || !is_dir($tmpPath)) {
            return 0;
        }

        $count = 0;
        $files = glob($tmpPath . '/*');
        if ($files === false) return 0;

        $oneDayAgo = time() - 86400; // 24 horas

        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $oneDayAgo) {
                // Evitar borrar .gitkeep
                if (basename($file) === '.gitkeep') {
                    continue;
                }
                if (@unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
