<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Jah\ActionScript\JAS;

$options = getopt('', ['level::', 'enemies::', 'shots::', 'particles::']);
$level = max(1, (int) ($options['level'] ?? 1));
$enemies = max(0, (int) ($options['enemies'] ?? 10));
$shots = max(0, (int) ($options['shots'] ?? 20));
$particles = max(0, (int) ($options['particles'] ?? 50));

$memoryStart = memory_get_usage(true);
$started = hrtime(true);

$game = JAS::game32('scale_game')
    ->size(960, 540)
    ->fps(60)
    ->title('Scale Game32');

$game->player('salk01')->at(80, 260)->energy(100)->speed(6)->weapon('pulse');
$game->level('scale_level_' . $level)
    ->background('scale')
    ->enemies($enemies)
    ->shots($shots)
    ->particles($particles)
    ->boss('scale_boss');

$html = $game->render();
$renderMs = (hrtime(true) - $started) / 1_000_000;
$memoryEnd = memory_get_usage(true);

echo json_encode([
    'level' => $level,
    'enemies' => $enemies,
    'shots' => $shots,
    'particles' => $particles,
    'render_ms' => round($renderMs, 3),
    'html_bytes' => strlen($html),
    'memory_delta' => $memoryEnd - $memoryStart,
    'pass_budget' => [
        'render_under_50ms' => $renderMs < 50,
        'html_under_2mb' => strlen($html) < 2_000_000,
        'memory_under_32mb' => ($memoryEnd - $memoryStart) < 33_554_432,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
