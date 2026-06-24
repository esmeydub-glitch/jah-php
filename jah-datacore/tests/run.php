<?php

declare(strict_types=1);

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/SecurityAgentTest.php';

use Jah\DataCore\Test\TestRunner;
use Jah\DataCore\Test\SecurityAgentTest;

$runner = new TestRunner();
$results = $runner->run();

$securityRunner = new SecurityAgentTest();
$securityResults = $securityRunner->run();

$runner->cleanup();

echo "\n=== SUMMARY ===\n";
$passed = count(array_filter($results, fn($r) => $r['ok']));
$total = count($results);
echo "DataCore: {$passed}/{$total}\n";

$secPassed = count($securityResults);
echo "Security: {$secPassed}/" . count($securityResults) . "\n";

if ($passed === $total && $secPassed === count($securityResults)) {
    echo "ALL TESTS PASSED\n";
    exit(0);
}
exit(1);