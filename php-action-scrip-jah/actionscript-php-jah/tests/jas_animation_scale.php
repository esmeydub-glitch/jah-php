<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Jah\ActionScript\JAS;

$options = getopt('', ['sprites::', 'tweens::']);
$sprites = max(0, (int) ($options['sprites'] ?? 1000));
$tweens = max(0, (int) ($options['tweens'] ?? 1000));

$started = hrtime(true);
$scene = JAS::scene('scale_scene');

for ($i = 0; $i < $sprites; $i++) {
    $scene->child(
        JAS::rectangle('sprite_' . $i)
            ->at($i % 800, ($i * 3) % 400)
            ->size(4, 4)
            ->fill('#00ffcc')
    );
}

for ($i = 0; $i < $tweens; $i++) {
    $scene->animation(
        JAS::tween('sprite_' . $i)
            ->from(['x' => 0, 'y' => 0, 'opacity' => 0.3])
            ->to(['x' => 100, 'y' => 50, 'opacity' => 1])
            ->duration(1000)
            ->ease('linear')
    );
}

$stage = JAS::stageBox('scale_stage', 800, 450)->children([$scene]);
$compiled = JAS::compile($stage);
$elapsedMs = (hrtime(true) - $started) / 1_000_000;

echo json_encode([
    'sprites' => $sprites,
    'tweens' => $tweens,
    'compile_ms' => round($elapsedMs, 3),
    'html_bytes' => strlen($compiled->html()),
    'css_bytes' => strlen($compiled->css()),
    'animations' => count($compiled->manifest()['animations'] ?? []),
    'memory_bytes' => memory_get_usage(true),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
