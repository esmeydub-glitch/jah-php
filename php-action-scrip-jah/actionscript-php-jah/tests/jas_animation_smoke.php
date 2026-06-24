<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Jah\ActionScript\JAS;

$sprite = JAS::sprite('logo')
    ->text('JAH')
    ->at(0, 100)
    ->size(100, 50);

$timeline = JAS::timeline('intro')
    ->add(
        JAS::tween('logo')
            ->from(['x' => 0, 'y' => 100, 'opacity' => 0])
            ->to(['x' => 300, 'y' => 100, 'opacity' => 1])
            ->duration(1000)
            ->ease('ease-out')
            ->onFinish('animation.logo.finished')
    );

$stage = JAS::stageBox('demo', 800, 400)->children([
    JAS::scene('main')->children([
        $sprite,
        JAS::circle('ball')->at(20, 20)->radius(10)->fill('#00ffcc'),
        JAS::rectangle('box')->at(40, 40)->size(20, 20)->fill('#ffaa00'),
        JAS::pixel('p1')->at(5, 5)->color('#ffffff'),
        JAS::line('ray')->from(0, 0)->to(100, 50)->stroke('#00ffcc')->width(2),
        $timeline,
    ]),
]);

$compiled = JAS::compile($stage);
$html = $compiled->html();
$css = $compiled->css();
$manifest = $compiled->manifest();

foreach ([
    'id="demo"',
    'id="logo"',
    'id="ball"',
    'id="box"',
    'id="p1"',
    '<svg',
] as $expected) {
    if (!str_contains($html, $expected)) {
        fwrite(STDERR, "missing html {$expected}\n");
        exit(1);
    }
}

foreach ([
    '@keyframes',
    '#logo',
    'animation:',
] as $expected) {
    if (!str_contains($css, $expected)) {
        fwrite(STDERR, "missing css {$expected}\n");
        exit(1);
    }
}

if (count($manifest['animations'] ?? []) !== 1) {
    fwrite(STDERR, "missing animation manifest\n");
    exit(1);
}

$events = $manifest['animations'][0]['events'] ?? [];
if (empty($events['finish']['token'])) {
    fwrite(STDERR, "missing SALK animation event token\n");
    exit(1);
}

echo "JAS animation smoke PASS\n";
