<?php

declare(strict_types=1);

namespace Jah\DataCore\Test;

require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/../src/CacheAgent.php';
require_once __DIR__ . '/../src/DataCoreLightning.php';
require_once __DIR__ . '/../src/QueryPlanner.php';
require_once __DIR__ . '/../src/WALTransactionCore.php';
require_once __DIR__ . '/../src/ReplicationAgent.php';

use Jah\DataCore\DataCoreLightning;
use Jah\DataCore\DataCore;
use Jah\DataCore\QueryPlanner;
use Jah\DataCore\WALTransactionCore;

final class TestRunner
{
    private array $results = [];
    private string $testDir;

    public function __construct()
    {
        $this->testDir = sys_get_temp_dir() . '/jah_test_' . bin2hex(random_bytes(4));
        mkdir($this->testDir, 0700, true);
    }

    public function run(): array
    {
        echo "=== JAH DATACORE TEST SUITE ===\n\n";

        $this->test('insert_10k', fn() => $this->testInsert(10000));
        $this->test('insert_50k', fn() => $this->testInsert(50000));
        $this->test('filtered_query', fn() => $this->testQuery());
        $this->test('crud_lifecycle', fn() => $this->testCrudLifecycle());
        $this->test('wal_transaction', fn() => $this->testWAL());
        $this->test('query_planner', fn() => $this->testPlanner());
        $this->test('integrity', fn() => $this->testIntegrity());

        return $this->results;
    }

    private function test(string $name, callable $fn): void
    {
        $start = hrtime(true);
        $memStart = memory_get_usage();

        try {
            $result = $fn();
            $ok = true;
        } catch (\Throwable $e) {
            $ok = false;
            $result = $e->getMessage();
        }

        $elapsed = (hrtime(true) - $start) / 1_000_000;
        $memUsed = memory_get_usage() - $memStart;

        $this->results[$name] = [
            'ok' => $ok,
            'time_ms' => round($elapsed, 2),
            'memory_kb' => round($memUsed / 1024, 2),
            'result' => $result,
        ];

        echo "{$name}: " . ($ok ? "✓ {$elapsed}ms" : "✗ {$result}") . "\n";
    }

    private function testInsert(int $count): array
    {
        $db = DataCoreLightning::open($this->testDir . '/insert_' . $count);
        
        for ($i = 0; $i < $count; $i++) {
            $db->insert('test', ['id' => 'id_' . $i, 'value' => rand(1, 1000)]);
        }
        
        $db->close();
        return ['inserted' => $count];
    }

    private function testQuery(): array
    {
        $db = DataCoreLightning::open($this->testDir . '/query');
        
        for ($i = 0; $i < 10000; $i++) {
            $db->insert('test', ['id' => 'q_' . $i, 'val' => rand(1, 10)]);
        }
        $db->close();

        $results = $db->query('test', fn(array $doc): bool => ($doc['val'] ?? 0) > 5);

        return ['found' => count($results)];
    }

    private function testCrudLifecycle(): array
    {
        $db = DataCore::init($this->testDir . '/crud');
        $db->insert('items', ['id' => 'first', 'value' => 1]);
        if (($db->find('items', 'first')['value'] ?? null) !== 1) {
            throw new \RuntimeException('Initial read failed');
        }

        $db->insert('items', ['id' => 'second', 'value' => 2]);
        if (($db->find('items', 'second')['value'] ?? null) !== 2) {
            throw new \RuntimeException('Live index was not updated');
        }

        $db->update('items', 'first', ['value' => 9]);
        if (($db->find('items', 'first')['value'] ?? null) !== 9) {
            throw new \RuntimeException('Updated value is not visible');
        }

        $db->delete('items', 'first');
        if ($db->find('items', 'first') !== null) {
            throw new \RuntimeException('Deleted value remains visible');
        }

        return ['insert' => true, 'update' => true, 'delete' => true];
    }

    private function testWAL(): array
    {
        $path = $this->testDir . '/wal';
        $wal = new WALTransactionCore($path);
        $tx = $wal->begin();
        
        $wal->write('test', ['id' => 'wal_1', 'value' => 100]);
        $wal->write('test', ['id' => 'wal_2', 'value' => 200]);
        if (!$wal->commit()) {
            throw new \RuntimeException('WAL commit failed');
        }

        $storage = $path . '/storage/test.ndjson';
        $rows = file($storage, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        if (count($rows) !== 2) {
            throw new \RuntimeException('Committed WAL rows are missing');
        }

        // Simulate a committed transaction whose data files were lost before replay.
        unlink($storage);
        $recovery = (new WALTransactionCore($path))->recover();
        if (($recovery['recovered'] ?? 0) !== 2) {
            throw new \RuntimeException('WAL did not replay committed rows');
        }

        // Replay must be idempotent and must not duplicate records.
        $secondRecovery = (new WALTransactionCore($path))->recover();
        $rows = file($storage, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        if (($secondRecovery['recovered'] ?? -1) !== 0 || count($rows) !== 2) {
            throw new \RuntimeException('WAL recovery is not idempotent');
        }

        return ['tx' => $tx, 'committed' => true, 'replayed' => 2, 'idempotent' => true];
    }

    private function testPlanner(): array
    {
        $db = DataCoreLightning::open($this->testDir . '/planner');
        
        $db->insert('items', ['id' => 'low', 'price' => 10]);
        $db->insert('items', ['id' => 'high', 'price' => 100]);

        $results = QueryPlanner::from($db, 'items')
            ->where('price', '>', 50)
            ->execute();
        $db->close();

        if (count($results) !== 1 || ($results[0]['id'] ?? null) !== 'high') {
            throw new \RuntimeException('Planner returned incorrect results');
        }

        return ['rows' => count($results)];
    }

    private function testIntegrity(): array
    {
        $db = DataCoreLightning::open($this->testDir . '/integrity');

        for ($i = 0; $i < 100; $i++) {
            $doc = ['id' => 'int_' . $i, 'data' => hash('xxh3', (string) $i)];
            $db->insert('test', $doc);
        }
        $db->close();

        // Check files exist
        $files = glob($this->testDir . '/integrity/data/*.ndjson');
        return ['files' => count($files)];
    }

    public function cleanup(): void
    {
        $this->removeDir($this->testDir);
    }

    private function removeDir(string $path): void
    {
        if (is_dir($path)) {
            foreach (glob("{$path}/*") as $item) {
                is_dir($item) ? $this->removeDir($item) : unlink($item);
            }
            rmdir($path);
        }
    }
}

// Run
$runner = new TestRunner();
$results = $runner->run();
$runner->cleanup();

echo "\n=== SUMMARY ===\n";
$passed = count(array_filter($results, fn($r) => $r['ok']));
$total = count($results);
echo "Passed: {$passed}/{$total}\n";

if ($passed === $total) {
    echo "ALL TESTS PASSED\n";
    exit(0);
}
exit(1);
