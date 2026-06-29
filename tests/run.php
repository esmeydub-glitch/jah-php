<?php

declare(strict_types=1);

$boot = require dirname(__DIR__) . '/app/bootstrap.php';
$config = $boot['config'];
$base = sys_get_temp_dir() . '/jah_product_tests_' . bin2hex(random_bytes(6));
$config['paths']['datacore_storage'] = $base . '/datacore';
$config['paths']['hot_storage'] = $base . '/pyramid';
$config['salk']['audit_file'] = $base . '/security/audit.jahl';

require_once dirname(__DIR__) . '/app/actions/MemoryActionScript.php';

use Jah\DataCore\DataCoreTurbo;
use Jah\DataCore\ReplicationAgent;
use Jah\DataCore\WorkerPool;
use Jah\DataCore\AgentFactory;
use Jah\Actions\ActionScript;
use Jah\Http\RequestGuard;
use Jah\Memory\TieredMemory;

$passed = 0;
$failed = 0;

function check(string $name, callable $test): void
{
    global $passed, $failed;
    try {
        $test();
        $passed++;
        echo "PASS {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL {$name}: {$e->getMessage()}\n";
    }
}

function expectTrue(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$memory = new TieredMemory($config['paths']['datacore_storage'], $config['paths']['hot_storage']);
$runtime = new MemoryActionScript($memory, $config);

check('csrf_tokens_are_enforced', function () use ($base): void {
    putenv('JAH_SESSION_PATH=' . $base . '/sessions');
    $token = RequestGuard::csrfToken();
    RequestGuard::assertCsrf($token);
    try {
        RequestGuard::assertCsrf('invalid');
    } catch (RuntimeException) {
        return;
    }
    throw new RuntimeException('invalid CSRF token was accepted');
});

check('book_summaries_store_generated_knowledge', function () use ($runtime): void {
    $message = 'Dame un resumen del libro Max Demian';
    $classification = ActionScript::run('memory.classify_input', ['message' => $message]);
    $decision = $classification['result'] ?? [];
    expectTrue(($decision['store'] ?? false) === true, 'book summary request was not selected for memory');
    expectTrue(($decision['store_response'] ?? false) === true, 'classifier selected the prompt instead of the generated summary');
    expectTrue(($decision['type'] ?? '') === 'knowledge_summary', 'unexpected summary classification');
    foreach (['Hazme una sinopsis de Demian', '¿De qué trata el libro Demian?', 'Summarize the book Demian'] as $variant) {
        $variantResult = ActionScript::run('memory.classify_input', ['message' => $variant]);
        expectTrue(($variantResult['result']['store_response'] ?? false) === true, "summary variant was not classified: {$variant}");
    }

    $stored = ActionScript::run('memory.store_interaction', [
        'message' => $message,
        'response' => 'Max Demian es una novela de Hermann Hesse sobre identidad, dualidad y crecimiento personal.',
        'collection' => 'book-memory',
        'classification' => $decision,
    ]);
    expectTrue(($stored['result']['assistant_stored'] ?? false) === true, 'Qwen summary was not persisted');
    expectTrue(($stored['result']['stored_source'] ?? '') === 'qwen_response', 'wrong memory source was persisted');

    $updated = ActionScript::run('memory.store_interaction', [
        'message' => $message,
        'response' => 'Max Demian es una novela de Hermann Hesse sobre identidad, dualidad, crecimiento personal y autoconocimiento.',
        'collection' => 'book-memory',
        'classification' => $decision,
    ]);
    expectTrue(($updated['result']['memory_id'] ?? '') === ($stored['result']['memory_id'] ?? null), 'repeated summary created a duplicate identity');

    $found = $runtime->search('Hermann Hesse', 'book-memory');
    $memories = $found['result']['memories'] ?? [];
    expectTrue(count($memories) === 1, 'stored book summary was not retrievable');
    expectTrue(str_contains((string)($memories[0]['content'] ?? ''), 'autoconocimiento'), 'retrieved memory does not contain the latest generated summary');
});

check('api_access_key_is_enforced', function () use ($config): void {
    $previous = $_ENV['JAH_API_KEY'] ?? null;
    $_ENV['JAH_API_KEY'] = 'test-access-key';
    putenv('JAH_API_KEY=test-access-key');
    $_SERVER['HTTP_X_JAH_API_KEY'] = 'wrong';
    try {
        RequestGuard::authorize($config);
    } catch (RuntimeException) {
        $_SERVER['HTTP_X_JAH_API_KEY'] = 'test-access-key';
        RequestGuard::authorize($config);
        unset($_SERVER['HTTP_X_JAH_API_KEY']);
        putenv('JAH_API_KEY');
        if ($previous === null) unset($_ENV['JAH_API_KEY']);
        else $_ENV['JAH_API_KEY'] = $previous;
        return;
    }
    throw new RuntimeException('invalid API access key was accepted');
});

check('tiers_are_deduplicated', function () use ($runtime): void {
    foreach (['hot', 'warm', 'cold'] as $tier) {
        $runtime->save($tier, ['content' => "marker_{$tier}"], $tier, 'alpha');
        $result = $runtime->search("marker_{$tier}", 'alpha');
        expectTrue(count($result['result']['memories'] ?? []) === 1, "{$tier} returned duplicate records");
    }
});

check('collections_are_isolated', function () use ($runtime): void {
    $runtime->save('isolated', ['content' => 'collection_marker'], 'warm', 'alpha');
    $other = $runtime->search('collection_marker', 'beta');
    expectTrue(($other['result']['memories'] ?? []) === [], 'memory crossed collection boundary');
});

check('delete_hides_archived_record', function () use ($runtime): void {
    $runtime->save('forgotten', ['content' => 'forget_marker'], 'cold', 'alpha');
    $runtime->delete('forgotten', 'alpha');
    $result = $runtime->search('forget_marker', 'alpha');
    expectTrue(($result['result']['memories'] ?? []) === [], 'deleted record was resurrected');
});

check('migration_respects_collection_and_tier', function () use ($runtime): void {
    $runtime->save('old', ['content' => 'migration_marker', '_ts' => time() - 90_000], 'hot', 'alpha');
    $first = $runtime->migrate('alpha');
    $second = $runtime->migrate('alpha');
    $record = $runtime->retrieve('old', 'alpha');
    expectTrue(($first['result']['hot_to_warm'] ?? 0) === 1, 'hot record did not migrate to warm');
    expectTrue(($second['result']['warm_to_cold'] ?? 0) === 1, 'warm record did not migrate to cold');
    expectTrue(($record['result']['memory']['_tier'] ?? '') === 'cold', 'migrated tier was not persisted');
});

check('sensitive_fields_are_rejected', function () use ($runtime): void {
    $result = $runtime->save('secret', ['password' => 'ordinary-password'], 'hot', 'alpha');
    expectTrue(($result['result']['saved'] ?? true) === false, 'sensitive field was stored');
});

check('batch_ids_are_retrievable', function () use ($base): void {
    $db = new DataCoreTurbo($base . '/batch', 10);
    $db->batchInsert('docs', [['content' => 'batch']]);
    $rows = $db->query('docs', static fn(array $doc): bool => true);
    $id = (string)($rows[0]['id'] ?? '');
    expectTrue($id !== '' && $db->find('docs', $id) !== null, 'generated batch id is not indexed');
    $db->close();
});

check('delimiter_ids_are_retrievable', function () use ($base): void {
    $db = new DataCoreTurbo($base . '/delimiter', 1);
    $db->insert('docs', ['id' => 'colon:id', 'content' => 'ok']);
    expectTrue($db->find('docs', 'colon:id') !== null, 'encoded index id was not found');
    $db->close();
});

check('inverted_index_rebuilds_automatically', function () use ($base): void {
    $path = $base . '/rebuild';
    $db = new DataCoreTurbo($path, 1);
    $db->insert('docs', ['id' => 'legacy', 'content' => 'automatic rebuild marker 2026']);
    $db->close();
    @unlink($path . '/index/terms/docs/.ready');

    $db = new DataCoreTurbo($path, 1);
    $results = $db->searchIndexed('docs', 'rebuild', 10);
    expectTrue(count($results) === 1 && ($results[0]['id'] ?? '') === 'legacy', 'index rebuild lost existing data');
    $db->close();
});

check('stale_postings_do_not_resurrect_updates', function () use ($runtime): void {
    $runtime->save('updated', ['content' => 'old_unique_term'], 'hot', 'index-update');
    $runtime->save('updated', ['content' => 'new_unique_term'], 'hot', 'index-update');
    $old = $runtime->search('old_unique_term', 'index-update');
    $new = $runtime->search('new_unique_term', 'index-update');
    expectTrue(($old['result']['memories'] ?? []) === [], 'stale posting returned obsolete content');
    expectTrue(count($new['result']['memories'] ?? []) === 1, 'updated term was not indexed');
});

check('search_reports_index_metrics', function () use ($runtime): void {
    $runtime->save('metric', ['content' => 'indexed_metric_term'], 'hot', 'metrics');
    $result = $runtime->search('indexed_metric_term', 'metrics');
    $metrics = $result['result']['metrics'] ?? [];
    expectTrue(($metrics['strategy'] ?? '') === 'datacore_inverted_index_v3', 'indexed strategy was not reported');
    expectTrue(isset($metrics['duration_ms'], $metrics['candidate_count']), 'search metrics are incomplete');
});

check('actionscript_rebuilds_datacore_indexes', function () use ($runtime): void {
    $result = $runtime->reindex('metrics');
    expectTrue(($result['success'] ?? false) === true, 'memory.reindex action failed');
    expectTrue(($result['result']['version'] ?? 0) === 3, 'unexpected DataCore index version');
    expectTrue(($result['result']['documents'] ?? 0) >= 1, 'reindex did not include active documents');
});

check('replication_is_signed_local_and_verifiable', function () use ($base): void {
    $primary = $base . '/replication-primary';
    $replica = $base . '/replication-copy';
    $agent = new ReplicationAgent($primary, 'test-replication-key-with-safe-length');
    $agent->addNode($replica);
    expectTrue($agent->replicate(['type' => 'memory.saved', 'id' => 'replicated']) === true, 'replication write failed');
    expectTrue($agent->verifyLog(), 'primary signature chain is invalid');
    expectTrue($agent->verifyLog($replica), 'replica signature chain is invalid');
    expectTrue(file_get_contents($primary . '/replication.log') === file_get_contents($replica . '/replication.log'), 'replica differs from primary');

    file_put_contents($replica . '/replication.log', 'tampered' . PHP_EOL, FILE_APPEND | LOCK_EX);
    expectTrue(!$agent->verifyLog($replica), 'tampered replica was accepted');
    expectTrue($agent->replicate(['type' => 'memory.updated', 'id' => 'replicated']) === true, 'replica did not recover');
    expectTrue($agent->verifyLog($replica), 'recovered replica chain is invalid');
});

check('worker_pool_reports_confirmed_inserts', function () use ($base): void {
    $poolPath = $base . '/worker-pool';
    $pool = new WorkerPool($poolPath, 2);
    $docs = [
        ['id' => 'pool-1', 'content' => 'one'],
        ['id' => 'pool-2', 'content' => 'two'],
        ['id' => 'pool-3', 'content' => 'three'],
        ['id' => 'pool-4', 'content' => 'four'],
    ];
    expectTrue($pool->parallelInsert('docs', $docs) === 4, 'worker pool returned an incorrect count');
    $storage = new \Jah\DataCore\StorageAgent($poolPath . '/data');
    expectTrue(count($storage->query('docs', static fn(array $row): bool => true)) === 4, 'worker pool did not persist every document');
    $storage->close();
});

check('transformer_map_performs_declared_mapping', function () use ($base): void {
    $factory = new AgentFactory($base . '/agents');
    $factory->create('transformer', [
        'id' => 'project-fields',
        'pipeline' => [[
            'name' => 'map',
            'fields' => ['label' => 'name', 'score' => 'value'],
        ]],
    ]);
    $mapped = $factory->execute('project-fields', [['name' => 'memory', 'value' => 9]]);
    expectTrue($mapped === [['label' => 'memory', 'score' => 9]], 'transformer map returned unchanged input');
});

$memory->close();
echo "SUMMARY {$passed}/" . ($passed + $failed) . "\n";
exit($failed === 0 ? 0 : 1);
