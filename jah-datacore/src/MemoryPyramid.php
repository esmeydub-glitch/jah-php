<?php

declare(strict_types=1);

namespace Jah\DataCore;

/**
 * Tres niveles de memoria para DataCore:
 * - Caliente (hot): LRU en RAM, 10k entries
 * - Tibia (warm): Archivo ndjson reciente, 100k entries
 * - Fría (cold): Archivos históricos comprimidos
 */
final class MemoryPyramid
{
    private string $basePath;
    private CacheAgent $hotCache;   // Memoria caliente
    private WarmMemory $warmCache;  // Memoria tibia
    private ColdMemory $coldStorage; // Memoria fría

    public function __construct(string $basePath)
    {
        $this->basePath = $basePath;
        $this->hotCache = new CacheAgent(10000);
        $this->warmCache = new WarmMemory($basePath . '/warm');
        $this->coldStorage = new ColdMemory($basePath . '/cold');
    }

    public function get(string $key): mixed
    {
        // 1. Hot cache (RAM)
        $val = $this->hotCache->get($key);
        if ($val !== null) {
            return $val;
        }

        // 2. Warm cache (reciente en disco)
        $val = $this->warmCache->get($key);
        if ($val !== null) {
            $this->hotCache->set($key, $val); // Promote to hot
            return $val;
        }

        // 3. Cold storage (histórico comprimido)
        return $this->coldStorage->get($key);
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $this->hotCache->set($key, $value);
        $this->warmCache->set($key, $value);
        
        // Promote to cold after TTL expiry
        if ($ttl > 0) {
            $this->coldStorage->schedule($key, $value, time() + $ttl);
        }
    }

    public function stats(): array
    {
        return [
            'hot_entries' => count($this->hotCache->getAll()),
            'warm_files' => count(glob($this->warmCache->getPath() . '/*.json')),
            'cold_files' => count(glob($this->coldStorage->getPath() . '/*.json.gz')),
        ];
    }
}

class WarmMemory
{
    private string $path;
    private array $index = [];

    public function __construct(string $path)
    {
        $this->path = $path;
        if (!is_dir($path)) {
            mkdir($path, 0700, true);
        }
    }

    public function get(string $key): mixed
    {
        $idx = $this->buildIndex();
        if (!isset($idx[$key])) {
            return null;
        }

        [$file, $offset] = $idx[$key];
        $handle = fopen($file, 'r');
        fseek($handle, $offset);
        $line = fgets($handle);
        fclose($handle);

        return json_decode($line, true)['payload'] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $file = $this->path . '/warm_' . date('Ymd') . '.ndjson';
        $offset = filesize($file) ?: 0;
        
        file_put_contents($file, json_encode(['key' => $key, 'payload' => $value]) . "\n", FILE_APPEND);
        
        // Update index in memory
        $this->index[$key] = [$file, $offset];
        file_put_contents(
            $this->path . '/.index', 
            "{$key}:{$offset}\n", 
            FILE_APPEND
        );
    }

    private function buildIndex(): array
    {
        if (!empty($this->index)) {
            return $this->index;
        }

        $indexFile = $this->path . '/.index';
        if (file_exists($indexFile)) {
            foreach (file($indexFile) as $line) {
                $parts = explode(':', trim($line));
                if (count($parts) >= 2) {
                    $this->index[$parts[0]] = [$this->path . '/warm_' . date('Ymd') . '.ndjson', (int) $parts[1]];
                }
            }
        }
        return $this->index;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}

class ColdMemory
{
    private string $path;
    private array $queue = [];

    public function __construct(string $path)
    {
        $this->path = $path;
        if (!is_dir($path)) {
            mkdir($path, 0700, true);
        }
    }

    public function get(string $key): mixed
    {
        foreach (glob($this->path . '/*.json.gz') as $file) {
            $decompressed = Compressor::decompressFile($file, sys_get_temp_dir() . '/tmp.json');
            $data = json_decode(file_get_contents(sys_get_temp_dir() . '/tmp.json'), true);
            if ($data && isset($data[$key])) {
                return $data[$key];
            }
        }
        return null;
    }

    public function schedule(string $key, mixed $value, int $expire): void
    {
        $this->queue[] = ['key' => $key, 'value' => $value, 'expire' => $expire];
        
        if (count($this->queue) > 5000) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        $now = time();
        $expired = array_filter($this->queue, fn($item) => $item['expire'] <= $now);
        
        if (empty($expired)) {
            return;
        }

        $data = [];
        foreach ($expired as $item) {
            $data[$item['key']] = $item['value'];
        }

        $file = $this->path . '/cold_' . time() . '.json.gz';
        $compressed = Compressor::compress(json_encode($data), 'gzip');
        file_put_contents($file, $compressed);

        // Remove flushed items
        $this->queue = array_diff_key($this->queue, $expired);
    }

    public function getPath(): string
    {
        return $this->path;
    }
}