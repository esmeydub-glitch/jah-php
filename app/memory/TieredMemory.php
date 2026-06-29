<?php

declare(strict_types=1);

namespace Jah\Memory;

use Jah\DataCore\DataCoreTurbo;
use Jah\DataCore\MemoryPyramid;
use Jah\DataCore\Compressor;
use Jah\DataCore\PhpSerializer;

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
    private array $lastSearchMetrics = [];

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
        $startedAt = hrtime(true);
        $collection = $this->normalizeCollection(is_array($collectionOrTiers) ? 'memories' : $collectionOrTiers);
        $queryLower = $this->normalizeSearchText($query);
        $terms = preg_split('/[^\p{L}\p{N}_-]+/u', $queryLower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $candidateLimit = max(50, min(300, $limit * 5));
        $candidates = $this->hot->searchIndexed($collection, $queryLower, $candidateLimit);

        $allResults = [];
        foreach ($candidates as $doc) {
            if (($doc['_deleted'] ?? false) === true || ($doc['role'] ?? '') === 'assistant') continue;
            if (!$this->matches($doc, $queryLower, $terms)) continue;
            $doc['_memory_tier'] = $this->normalizeTier((string)($doc['_tier'] ?? 'hot'));
            $allResults[] = $doc;
        }

        usort($allResults, static function (array $a, array $b): int {
            $score = (int)($b['_search_score'] ?? 0) <=> (int)($a['_search_score'] ?? 0);
            return $score !== 0 ? $score : ((int)($b['_ts'] ?? 0) <=> (int)($a['_ts'] ?? 0));
        });

        $results = array_slice($allResults, 0, $limit);
        $this->lastSearchMetrics = [
            'strategy' => 'datacore_inverted_index_v3',
            'collection' => $collection,
            'candidate_count' => count($candidates),
            'result_count' => count($results),
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 3),
        ];
        return $results;
    }

    public function getLastSearchMetrics(): array
    {
        return $this->lastSearchMetrics;
    }

    public function store(string $first, mixed $second, mixed $third = 'hot', string $collection = 'memories'): bool
    {
        $collection = $this->normalizeCollection($collection);
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
        $content['_collection'] = $collection;

        $this->hot->insert($collection, $content);
        $this->hot->flush();
        $this->pyramid->set($this->storageKey($collection, $id), $content, 0, $tier);

        if ($tier === 'warm') {
            $this->appendWarm($id, $content);
        } elseif ($tier === 'cold') {
            $this->appendCold($id, $content);
        }

        return true;
    }

    public function retrieve(string $tierOrId, ?string $key = null, string $collection = 'memories'): ?array
    {
        $collection = $this->normalizeCollection($collection);
        $id = $key ?? $tierOrId;
        $fromTurbo = $this->hot->find($collection, $id);
        if ($fromTurbo !== null) {
            return $fromTurbo;
        }

        $fromPyramid = $this->pyramid->get($this->storageKey($collection, $id));
        if ($fromPyramid === null && $collection === 'memories') {
            $fromPyramid = $this->pyramid->get($id);
        }
        if (is_array($fromPyramid) && ($fromPyramid['_deleted'] ?? false) === true) {
            return null;
        }
        return is_array($fromPyramid) ? $fromPyramid : null;
    }

    public function forget(string $id, string $collection = 'memories'): void
    {
        $collection = $this->normalizeCollection($collection);
        $this->hot->delete($collection, $id);
        $this->pyramid->set(
            $this->storageKey($collection, $id),
            ['id' => $id, '_collection' => $collection, '_deleted' => true, '_ts' => time()],
            0,
            'warm'
        );
    }

    public function migrate(string $collection = 'memories'): array
    {
        $collection = $this->normalizeCollection($collection);
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
                $this->pyramid->set($this->storageKey($collection, $id), $doc, 0, 'warm');
                $this->hot->insert($collection, $doc);
                $migrated['hot_to_warm']++;
            } elseif ($currentTier === 'warm' && $age > self::WARM_TTL) {
                $doc['_tier'] = 'cold';
                $this->appendCold($id, $doc);
                $this->pyramid->set($this->storageKey($collection, $id), $doc, 0, 'cold');
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

    public function stats(string $collection = 'memories'): array
    {
        $collection = $this->normalizeCollection($collection);
        return array_merge($this->pyramid->stats(), [
            'hot_documents' => $this->hot->getStats()['documents'] ?? 0,
            'warm_records' => $this->countSerializedLines($this->runtimeMemoryPath . '/warm'),
            'cold_files' => count(glob($this->runtimeMemoryPath . '/cold/*.jahp.gz') ?: []),
            'search_index' => $this->hot->getIndexStats($collection),
        ]);
    }

    public function rebuildIndexes(string $collection = 'memories'): array
    {
        return $this->hot->rebuildIndexes($this->normalizeCollection($collection));
    }

    public function close(): void
    {
        $this->hot->close();
    }

    private function appendWarm(string $id, array $doc): void
    {
        $dir = $this->runtimeMemoryPath . '/warm';
        if (!is_dir($dir)) mkdir($dir, 0700, true);
        $file = $dir . '/warm_' . date('Ymd') . '.jahl';
        $record = PhpSerializer::encode(['key' => $id, 'payload' => $doc]);
        if ($record !== false) {
            file_put_contents($file, $record . "\n", FILE_APPEND | LOCK_EX);
        }
    }

    private function appendCold(string $id, array $doc): void
    {
        $dir = $this->runtimeMemoryPath . '/cold';
        if (!is_dir($dir)) mkdir($dir, 0700, true);
        $file = $dir . '/cold_' . hash('sha256', $id) . '_' . time() . '.jahp.gz';
        $payload = PhpSerializer::encode([$id => $doc]);
        if ($payload !== '') {
            file_put_contents($file, Compressor::compress($payload, 'gzip'), LOCK_EX);
        }
    }

    private function readWarmRecords(string $collection): array
    {
        $records = [];
        $dir = $this->runtimeMemoryPath . '/warm';
        foreach (glob($dir . '/*.jahl') ?: [] as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $record = PhpSerializer::decode($line, true);
                $doc = is_array($record) ? ($record['payload'] ?? null) : null;
                if (!is_array($doc)) continue;
                if ((string)($doc['_collection'] ?? 'memories') !== $collection) continue;
                $records[] = $doc;
            }
        }
        return $records;
    }

    private function readColdRecords(string $collection): array
    {
        $records = [];
        $dir = $this->runtimeMemoryPath . '/cold';
        foreach (glob($dir . '/*.jahp.gz') ?: [] as $file) {
            $tmp = tempnam(sys_get_temp_dir(), 'jah_cold_');
            if ($tmp === false) continue;
            $ok = Compressor::decompressFile($file, $tmp, 'gzip');
            $raw = $ok ? file_get_contents($tmp) : false;
            @unlink($tmp);
            $data = $raw !== false ? PhpSerializer::decode($raw, true) : null;
            if (!is_array($data)) continue;
            foreach ($data as $doc) {
                if (!is_array($doc)) continue;
                if ((string)($doc['_collection'] ?? 'memories') !== $collection) continue;
                $records[] = $doc;
            }
        }
        return $records;
    }

    private function matches(array $doc, string $queryLower, array $terms): bool
    {
        $searchable = $this->normalizeSearchText(PhpSerializer::searchable($doc));
        if ($queryLower !== '' && str_contains($searchable, $queryLower)) return true;
        foreach ($terms as $term) {
            if ($term !== '' && str_contains($searchable, $term)) return true;
        }
        return false;
    }

    private function normalizeSearchText(string $text): string
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        return strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    private function countSerializedLines(string $dir): int
    {
        $count = 0;
        foreach (glob($dir . '/*.jahl') ?: [] as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $count += is_array($lines) ? count($lines) : 0;
        }
        return $count;
    }

    private function normalizeTier(string $tier): string
    {
        return in_array($tier, ['hot', 'warm', 'cold'], true) ? $tier : 'hot';
    }

    private function storageKey(string $collection, string $id): string
    {
        return $collection . ':' . $id;
    }

    private function normalizeCollection(string $collection): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_-]/', '_', $collection) ?: 'memories';
        return trim($clean, '_') !== '' ? $clean : 'memories';
    }
}
