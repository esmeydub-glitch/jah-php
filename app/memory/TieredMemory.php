<?php

declare(strict_types=1);

namespace Jah\Memory;

use Jah\DataCore\DataCoreTurbo;
use Jah\DataCore\MemoryPyramid;
use Jah\DataCore\Compressor;

/**
 * TieredMemory
 * Pure PHP wrapper over DataCoreTurbo + MemoryPyramid.
 * Keeps compatibility with both call styles used in the original project:
 * - store($id, $content, $tier)
 * - store($tier, $key, $data)
 */
class TieredMemory
{
    private DataCoreTurbo $hot;
    private MemoryPyramid $pyramid;
    private string $storagePath;
    private string $pyramidPath;
    private string $runtimeMemoryPath;

    private const HOT_TTL = 3600;
    private const WARM_TTL = 86400;
    private const COLD_TTL = 604800;

    public function __construct(string $storagePath, string|array $hotStoragePath = '')
    {
        if (is_array($hotStoragePath)) {
            // Legacy constructor compatibility: base path + tier config.
            $base = rtrim($storagePath, '/');
            $this->storagePath = $base . '/datacore';
            $this->pyramidPath = $base . '/pyramid';
        } else {
            $this->storagePath = rtrim($storagePath, '/');
            $this->pyramidPath = rtrim($hotStoragePath !== '' ? $hotStoragePath : dirname($this->storagePath) . '/pyramid', '/');
        }

        $this->runtimeMemoryPath = dirname($this->storagePath);
        $this->hot = new DataCoreTurbo($this->storagePath, 500);
        $this->pyramid = new MemoryPyramid($this->pyramidPath);

        foreach (['warm', 'cold'] as $dir) {
            $path = $this->runtimeMemoryPath . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0700, true);
            }
        }
    }

    public function search(string $query, string|array $collectionOrTiers = 'memories', int $limit = 20): array
    {
        $collection = is_array($collectionOrTiers) ? 'memories' : $collectionOrTiers;
        $queryLower = strtolower($query);
        $terms = array_values(array_filter(explode(' ', $queryLower)));
        $allResults = [];

        $hotResults = $this->hot->query($collection, static function (array $doc) use ($queryLower, $terms): bool {
            if (($doc['_deleted'] ?? false) === true) return false;
            if (($doc['role'] ?? '') === 'assistant') return false;
            $searchable = strtolower(json_encode($doc, JSON_UNESCAPED_UNICODE) ?: '');
            if ($queryLower !== '' && str_contains($searchable, $queryLower)) return true;
            foreach ($terms as $term) {
                if ($term !== '' && str_contains($searchable, $term)) return true;
            }
            return false;
        });

        foreach ($hotResults as $doc) {
            $doc['_memory_tier'] = $doc['_tier'] ?? 'hot';
            $allResults[] = $doc;
        }

        foreach ($this->readWarmRecords($queryLower, $terms) as $doc) {
            $doc['_memory_tier'] = 'warm';
            $allResults[] = $doc;
        }

        foreach ($this->readColdRecords($queryLower, $terms) as $doc) {
            $doc['_memory_tier'] = 'cold';
            $allResults[] = $doc;
        }

        usort($allResults, static fn(array $a, array $b): int => (int)($b['_ts'] ?? 0) <=> (int)($a['_ts'] ?? 0));

        return array_slice($allResults, 0, $limit);
    }

    public function store(string $first, mixed $second, mixed $third = 'hot'): bool
    {
        if (is_string($second) && is_array($third)) {
            // Legacy style: store($tier, $key, $data)
            $tier = $this->normalizeTier($first);
            $id = $second;
            $content = $third;
        } else {
            // Current style: store($id, $content, $tier)
            $id = $first;
            $content = is_array($second) ? $second : ['content' => (string)$second];
            $tier = $this->normalizeTier(is_string($third) ? $third : 'hot');
        }

        $content['id'] = $id;
        $content['_ts'] = $content['_ts'] ?? time();
        $content['_tier'] = $tier;

        $this->hot->insert('memories', $content);
        $this->hot->flush();
        $this->pyramid->set($id, $content, $tier === 'cold' ? self::COLD_TTL : 0);

        if ($tier === 'warm') {
            $this->appendWarm($id, $content);
        } elseif ($tier === 'cold') {
            $this->appendCold($id, $content);
        }

        return true;
    }

    public function retrieve(string $tierOrId, ?string $key = null): ?array
    {
        $id = $key ?? $tierOrId;
        $fromTurbo = $this->hot->find('memories', $id);
        if ($fromTurbo !== null) {
            return $fromTurbo;
        }

        $fromPyramid = $this->pyramid->get($id);
        if (is_array($fromPyramid) && ($fromPyramid['_deleted'] ?? false) === true) {
            return null;
        }
        return is_array($fromPyramid) ? $fromPyramid : null;
    }

    public function forget(string $id, string $collection = 'memories'): void
    {
        $this->hot->delete($collection, $id);
        $this->pyramid->set($id, ['id' => $id, '_deleted' => true, '_ts' => time()]);
    }

    public function migrate(string $collection = 'memories'): array
    {
        $migrated = ['hot_to_warm' => 0, 'warm_to_cold' => 0];
        $now = time();

        $allDocs = $this->hot->query($collection, static fn(array $doc): bool => ($doc['_deleted'] ?? false) !== true);

        foreach ($allDocs as $doc) {
            $id = (string)($doc['id'] ?? '');
            if ($id === '') continue;

            $ts = (int)($doc['_ts'] ?? $now);
            $currentTier = $this->normalizeTier((string)($doc['_tier'] ?? 'hot'));
            $age = $now - $ts;

            if ($currentTier === 'hot' && $age > self::HOT_TTL) {
                $doc['_tier'] = 'warm';
                $this->appendWarm($id, $doc);
                $this->hot->insert($collection, $doc);
                $migrated['hot_to_warm']++;
            } elseif ($currentTier === 'warm' && $age > self::WARM_TTL) {
                $doc['_tier'] = 'cold';
                $this->appendCold($id, $doc);
                $this->hot->insert($collection, $doc);
                $migrated['warm_to_cold']++;
            }
        }

        $this->hot->flush();
        return $migrated;
    }

    public function migrateTiers(): array
    {
        $summary = $this->migrate('memories');
        $events = [];
        foreach ($summary as $path => $count) {
            for ($i = 0; $i < $count; $i++) {
                [$from, $to] = explode('_to_', $path);
                $events[] = ['key' => 'memory', 'from' => $from, 'to' => $to];
            }
        }
        return $events;
    }

    public function stats(): array
    {
        return array_merge($this->pyramid->stats(), [
            'hot_documents' => $this->hot->getStats()['documents'] ?? 0,
            'warm_records' => $this->countNdjson($this->runtimeMemoryPath . '/warm'),
            'cold_files' => count(glob($this->runtimeMemoryPath . '/cold/*.json.gz') ?: []),
        ]);
    }

    public function close(): void
    {
        $this->hot->close();
    }

    private function appendWarm(string $id, array $doc): void
    {
        $dir = $this->runtimeMemoryPath . '/warm';
        if (!is_dir($dir)) mkdir($dir, 0700, true);
        $file = $dir . '/warm_' . date('Ymd') . '.ndjson';
        $record = json_encode(['key' => $id, 'payload' => $doc], JSON_UNESCAPED_UNICODE);
        if ($record !== false) {
            file_put_contents($file, $record . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    private function appendCold(string $id, array $doc): void
    {
        $dir = $this->runtimeMemoryPath . '/cold';
        if (!is_dir($dir)) mkdir($dir, 0700, true);
        $file = $dir . '/cold_' . $id . '_' . time() . '.json.gz';
        $json = json_encode([$id => $doc], JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            file_put_contents($file, Compressor::compress($json, 'gzip'), LOCK_EX);
        }
    }

    private function readWarmRecords(string $queryLower, array $terms): array
    {
        $records = [];
        $dir = $this->runtimeMemoryPath . '/warm';
        foreach (glob($dir . '/*.ndjson') ?: [] as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $record = json_decode($line, true);
                $doc = is_array($record) ? ($record['payload'] ?? null) : null;
                if (!is_array($doc) || ($doc['_deleted'] ?? false) === true || ($doc['role'] ?? '') === 'assistant') continue;
                if ($this->matches($doc, $queryLower, $terms)) $records[] = $doc;
            }
        }
        return $records;
    }

    private function readColdRecords(string $queryLower, array $terms): array
    {
        $records = [];
        $dir = $this->runtimeMemoryPath . '/cold';
        foreach (glob($dir . '/*.json.gz') ?: [] as $file) {
            $tmp = tempnam(sys_get_temp_dir(), 'jah_cold_');
            if ($tmp === false) continue;
            $ok = Compressor::decompressFile($file, $tmp, 'gzip');
            $raw = $ok ? file_get_contents($tmp) : false;
            @unlink($tmp);
            $data = $raw !== false ? json_decode($raw, true) : null;
            if (!is_array($data)) continue;
            foreach ($data as $doc) {
                if (!is_array($doc) || ($doc['_deleted'] ?? false) === true || ($doc['role'] ?? '') === 'assistant') continue;
                if ($this->matches($doc, $queryLower, $terms)) $records[] = $doc;
            }
        }
        return $records;
    }

    private function matches(array $doc, string $queryLower, array $terms): bool
    {
        $searchable = strtolower(json_encode($doc, JSON_UNESCAPED_UNICODE) ?: '');
        if ($queryLower !== '' && str_contains($searchable, $queryLower)) return true;
        foreach ($terms as $term) {
            if ($term !== '' && str_contains($searchable, $term)) return true;
        }
        return false;
    }

    private function countNdjson(string $dir): int
    {
        $count = 0;
        foreach (glob($dir . '/*.ndjson') ?: [] as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $count += is_array($lines) ? count($lines) : 0;
        }
        return $count;
    }

    private function normalizeTier(string $tier): string
    {
        return in_array($tier, ['hot', 'warm', 'cold'], true) ? $tier : 'hot';
    }
}
