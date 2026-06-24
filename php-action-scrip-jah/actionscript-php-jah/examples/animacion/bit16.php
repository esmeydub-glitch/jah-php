<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use Jah\ActionScript\JAS;

function pixelBlock(string $id, array $matrix, array $palette, int $pixelSize, int $x, int $y): \Jah\ActionScript\Components\Sprite
{
    $sprite = JAS::sprite($id)
        ->at($x, $y)
        ->style([
            'position' => 'absolute',
            'width' => count($matrix[0]) * $pixelSize . 'px',
            'height' => count($matrix) * $pixelSize . 'px',
            'display' => 'grid',
            'grid-template-columns' => 'repeat(' . count($matrix[0]) . ', ' . $pixelSize . 'px)',
            'grid-auto-rows' => $pixelSize . 'px',
            'image-rendering' => 'pixelated',
        ]);

    foreach ($matrix as $rowIndex => $row) {
        foreach ($row as $colIndex => $colorIndex) {
            $color = $palette[$colorIndex] ?? 'transparent';
            $sprite->child(
                JAS::pixel($id . '_px_' . $rowIndex . '_' . $colIndex)
                    ->color($color)
                    ->style([
                        'position' => 'static',
                        'width' => $pixelSize . 'px',
                        'height' => $pixelSize . 'px',
                    ])
            );
        }
    }

    return $sprite;
}

$palette = [
    0 => 'transparent',
    1 => '#1b1f4a',
    2 => '#3b82f6',
    3 => '#7dd3fc',
    4 => '#f7f7f2',
    5 => '#ffcc33',
    6 => '#ff6b35',
];

$shipMatrix = [
    [0,0,0,3,3,0,0,0],
    [0,0,3,4,4,3,0,0],
    [0,3,2,2,2,2,3,0],
    [3,2,2,5,5,2,2,3],
    [3,2,2,2,2,2,2,3],
    [0,0,6,0,0,6,0,0],
];

$coinMatrix = [
    [0,5,5,5,0],
    [5,4,5,4,5],
    [5,5,5,5,5],
    [5,4,5,4,5],
    [0,5,5,5,0],
];

$hero = pixelBlock('hero_ship', $shipMatrix, $palette, 10, 80, 210);
$coin = pixelBlock('coin', $coinMatrix, $palette, 8, 620, 120);

$stars = [];
for ($i = 0; $i < 42; $i++) {
    $stars[] = JAS::pixel('star_' . $i)
        ->at(($i * 47) % 780, ($i * 83) % 420)
        ->color($i % 3 === 0 ? '#7dd3fc' : '#f7f7f2')
        ->style([
            'width' => ($i % 2 + 1) . 'px',
            'height' => ($i % 2 + 1) . 'px',
        ]);
}

$timeline = JAS::timeline('bit16_timeline')
    ->add(
        JAS::tween('hero_ship')
            ->from(['x' => 80, 'y' => 210, 'scale' => 1])
            ->to(['x' => 560, 'y' => 170, 'scale' => 1.2])
            ->duration(2600)
            ->ease('cubic-bezier(.2,.8,.2,1)')
            ->onFinish('bit16.hero.arrived')
    )
    ->add(
        JAS::tween('coin')
            ->from(['x' => 620, 'y' => 120, 'rotate' => 0, 'scale' => 1])
            ->to(['x' => 620, 'y' => 120, 'rotate' => 360, 'scale' => 1.4])
            ->duration(1200)
            ->ease('linear')
            ->loop('infinite')
    );

foreach ($stars as $i => $star) {
    $timeline->add(
        JAS::tween('star_' . $i)
            ->from(['x' => 0, 'opacity' => 0.25])
            ->to(['x' => -120, 'opacity' => 1])
            ->duration(1800 + (($i % 5) * 300))
            ->ease('linear')
            ->loop('infinite')
    );
}

$stage = JAS::stageBox('bit16_stage', 800, 450)->children([
    JAS::scene('space')->children([
        ...$stars,
        JAS::rectangle('ground')->at(0, 390)->size(800, 60)->fill('#1b1f4a'),
        JAS::rectangle('neon_line')->at(0, 388)->size(800, 4)->fill('#00ffcc'),
        JAS::sprite('title')->text('16-BIT JAH')->at(40, 34)->style([
            'font-family' => 'monospace',
            'font-size' => '32px',
            'font-weight' => '800',
            'color' => '#f7f7f2',
            'text-shadow' => '4px 4px 0 #3b82f6',
        ]),
        $hero,
        $coin,
        $timeline,
    ]),
]);

$compiled = JAS::compile($stage);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JAS 16-bit</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #080b16; color: #f7f7f2; font-family: monospace; }
        .asjah-stage { border: 4px solid #334155; background: linear-gradient(#0f172a, #111827); box-shadow: 0 0 0 4px #020617, 0 24px 80px #000; image-rendering: pixelated; }
        .asjah-scene { position: relative; width: 100%; height: 100%; overflow: hidden; }
        .asjah-pixel { box-sizing: border-box; }
    </style>
    <?= $compiled->styleTag(); ?>
</head>
<body>
    <?= $compiled->html(); ?>
</body>
</html>
