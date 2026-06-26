<?php

declare(strict_types=1);

namespace Jah\Memory;

use RuntimeException;

class TieredMemory
{
    private string $basePath;
    private array $tierConfig;
    private array $index = [];

    private const TIERS = ['hot', 'warm', 'cold'];
    private const INDEX_FILE = 'memory_index.json';

    public function __construct(string $basePath, array $tierConfig = [])
    {
        $this->basePath = rtrim($basePath, '/');
        $this->tierConfig = array_merge([
            'hot' => ['ttl' => 3600, 'max_files' => 1000],
            'warm' => ['ttl' => 86400, 'max_files' => 5000],
            'cold' => ['ttl' => 604800, 'max_files' => 50000],
        ], $tierConfig);

        $this->ensureDirectories();
        $this->loadIndex();
    }

    private function ensureDirectories(): void
    {
        foreach (self::TIERS as $tier) {
            $path = $this->basePath . '/' . $tier;
            if (!is_dir($path)) {
                mkdir($path, 0775, true);
            }
        }
    }

    private function loadIndex(): void
    {
        $indexFile = $this->basePath . '/' . self::INDEX_FILE;
        if (file_exists($indexFile)) {
            $content = file_get_contents($indexFile);
            $this->index = json_decode($content, true) ?? [];
        }
    }

    private function saveIndex(): void
    {
        $indexFile = $this->basePath . '/' . self::INDEX_FILE;
        file_put_contents($indexFile, json_encode($this->index, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public function store(string $tier, string $key, array $data): bool
    {
        if (!in_array($tier, self::TIERS, true)) {
            throw new RuntimeException("Invalid tier: {$tier}");
        }

        $tierPath = $this->basePath . '/' . $tier;
        $filePath = $tierPath . '/' . $this->sanitizeKey($key) . '.json';

        $record = [
            'key' => $key,
            'tier' => $tier,
            'data' => $data,
            'metadata' => [
                'created_at' => time(),
                'updated_at' => time(),
                'access_count' => 0,
                'size_bytes' => 0,
            ],
            'tags' => $data['tags'] ?? [],
        ];

        $json = json_encode($record, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $record['metadata']['size_bytes'] = strlen($json);

        file_put_contents($filePath, $json);

        $this->index[$key] = [
            'tier' => $tier,
            'file' => $filePath,
            'tags' => $record['tags'],
            'created_at' => $record['metadata']['created_at'],
            'updated_at' => $record['metadata']['updated_at'],
        ];

        $this->saveIndex();
        return true;
    }

    public function retrieve(string $tier, string $key): ?array
    {
        if (!in_array($tier, self::TIERS, true)) {
            return null;
        }

        $filePath = $this->basePath . '/' . $tier . '/' . $this->sanitizeKey($key) . '.json';

        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        $record = json_decode($content, true);

        if ($record) {
            $record['metadata']['access_count']++;
            $record['metadata']['last_accessed'] = time();
            file_put_contents($filePath, json_encode($record, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }

        return $record;
    }

    public function search(string $query, array $tiers = ['hot', 'warm', 'cold'], int $limit = 20): array
    {
        $results = [];
        $queryLower = strtolower($query);
        $queryTerms = array_filter(explode(' ', $queryLower));

        foreach ($tiers as $tier) {
            if (!in_array($tier, self::TIERS, true)) {
                continue;
            }

            $tierPath = $this->basePath . '/' . $tier;
            $files = glob($tierPath . '/*.json') ?: [];

            foreach ($files as $file) {
                $content = file_get_contents($file);
                $record = json_decode($content, true);

                if (!$record) {
                    continue;
                }

                $score = $this->calculateRelevance($record, $queryTerms, $queryLower);

                if ($score > 0) {
                    $results[] = [
                        'key' => $record['key'],
                        'tier' => $tier,
                        'score' => $score,
                        'data' => $record['data'],
                        'metadata' => $record['metadata'],
                    ];
                }
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    public function getByTags(array $tags, array $tiers = ['hot', 'warm', 'cold'], int $limit = 20): array
    {
        $results = [];
        $tagsLower = array_map('strtolower', $tags);

        foreach ($tiers as $tier) {
            if (!in_array($tier, self::TIERS, true)) {
                continue;
            }

            $tierPath = $this->basePath . '/' . $tier;
            $files = glob($tierPath . '/*.json') ?: [];

            foreach ($files as $file) {
                $content = file_get_contents($file);
                $record = json_decode($content, true);

                if (!$record || empty($record['tags'])) {
                    continue;
                }

                $recordTagsLower = array_map('strtolower', $record['tags']);
                $matches = array_intersect($tagsLower, $recordTagsLower);

                if (!empty($matches)) {
                    $results[] = [
                        'key' => $record['key'],
                        'tier' => $tier,
                        'score' => count($matches),
                        'data' => $record['data'],
                        'metadata' => $record['metadata'],
                    ];
                }
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    public function moveTier(string $key, string $toTier): bool
    {
        if (!in_array($toTier, self::TIERS, true)) {
            return false;
        }

        $entry = $this->index[$key] ?? null;
        if (!$entry) {
            return false;
        }

        $fromTier = $entry['tier'];
        if ($fromTier === $toTier) {
            return true;
        }

        $fromPath = $this->basePath . '/' . $fromTier . '/' . $this->sanitizeKey($key) . '.json';
        $toPath = $this->basePath . '/' . $toTier . '/' . $this->sanitizeKey($key) . '.json';

        if (!file_exists($fromPath)) {
            return false;
        }

        rename($fromPath, $toPath);

        $this->index[$key]['tier'] = $toTier;
        $this->index[$key]['updated_at'] = time();
        $this->saveIndex();

        return true;
    }

    public function listAll(string $tier = '', int $limit = 100, int $offset = 0): array
    {
        $results = [];

        if ($tier !== '' && in_array($tier, self::TIERS, true)) {
            $tiers = [$tier];
        } else {
            $tiers = self::TIERS;
        }

        foreach ($tiers as $t) {
            $tierPath = $this->basePath . '/' . $t;
            $files = glob($tierPath . '/*.json') ?: [];

            foreach ($files as $file) {
                $content = file_get_contents($file);
                $record = json_decode($content, true);

                if ($record) {
                    $results[] = [
                        'key' => $record['key'],
                        'tier' => $t,
                        'tags' => $record['tags'] ?? [],
                        'created_at' => $record['metadata']['created_at'] ?? 0,
                        'size_bytes' => $record['metadata']['size_bytes'] ?? 0,
                    ];
                }
            }
        }

        $total = count($results);
        $results = array_slice($results, $offset, $limit);

        return [
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'items' => $results,
        ];
    }

    public function delete(string $key): bool
    {
        $entry = $this->index[$key] ?? null;
        if (!$entry) {
            return false;
        }

        $filePath = $this->basePath . '/' . $entry['tier'] . '/' . $this->sanitizeKey($key) . '.json';

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        unset($this->index[$key]);
        $this->saveIndex();

        return true;
    }

    public function getStats(): array
    {
        $stats = [];

        foreach (self::TIERS as $tier) {
            $tierPath = $this->basePath . '/' . $tier;
            $files = glob($tierPath . '/*.json') ?: [];
            $totalSize = 0;

            foreach ($files as $file) {
                $totalSize += filesize($file);
            }

            $stats[$tier] = [
                'count' => count($files),
                'total_size_bytes' => $totalSize,
                'max_files' => $this->tierConfig[$tier]['max_files'],
                'ttl_seconds' => $this->tierConfig[$tier]['ttl'],
            ];
        }

        return $stats;
    }

    public function migrateTiers(): array
    {
        $migrated = [];
        $now = time();

        foreach ($this->index as $key => $entry) {
            $tier = $entry['tier'];
            $createdAt = $entry['created_at'] ?? $now;
            $age = $now - $createdAt;
            $ttl = $this->tierConfig[$tier]['ttl'];

            if ($age > $ttl) {
                $nextTier = $this->getNextTier($tier);
                if ($nextTier && $this->moveTier($key, $nextTier)) {
                    $migrated[] = ['key' => $key, 'from' => $tier, 'to' => $nextTier];
                }
            }
        }

        return $migrated;
    }

    private function getNextTier(string $tier): ?string
    {
        return match ($tier) {
            'hot' => 'warm',
            'warm' => 'cold',
            'cold' => null,
            default => null,
        };
    }

    private function calculateRelevance(array $record, array $terms, string $query): float
    {
        $score = 0.0;
        $searchable = strtolower(json_encode($record['data']) . ' ' . implode(' ', $record['tags'] ?? []));

        foreach ($terms as $term) {
            if (str_contains($searchable, $term)) {
                $score += 1.0;
            }
        }

        if (str_contains($searchable, $query)) {
            $score += 2.0;
        }

        return $score;
    }

    private function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    }
}
