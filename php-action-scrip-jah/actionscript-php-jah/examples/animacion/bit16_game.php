<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use Jah\ActionScript\Components\Sprite;
use Jah\ActionScript\JAS;

function gamePixelBlock(string $id, array $matrix, array $palette, int $pixelSize): Sprite
{
    $sprite = JAS::sprite($id)->style([
        'position' => 'absolute',
        'left' => '0',
        'top' => '0',
        'width' => count($matrix[0]) * $pixelSize . 'px',
        'height' => count($matrix) * $pixelSize . 'px',
        'display' => 'grid',
        'grid-template-columns' => 'repeat(' . count($matrix[0]) . ', ' . $pixelSize . 'px)',
        'grid-auto-rows' => $pixelSize . 'px',
        'image-rendering' => 'pixelated',
        'will-change' => 'transform',
    ]);

    foreach ($matrix as $rowIndex => $row) {
        foreach ($row as $colIndex => $colorIndex) {
            $sprite->child(
                JAS::pixel($id . '_px_' . $rowIndex . '_' . $colIndex)
                    ->color($palette[$colorIndex] ?? 'transparent')
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
    1 => '#0f172a',
    2 => '#2563eb',
    3 => '#7dd3fc',
    4 => '#f7f7f2',
    5 => '#ffcc33',
    6 => '#ef4444',
    7 => '#22c55e',
];

$ship = [
    [0,0,0,3,3,0,0,0],
    [0,0,3,4,4,3,0,0],
    [0,3,2,2,2,2,3,0],
    [3,2,2,5,5,2,2,3],
    [3,2,2,2,2,2,2,3],
    [0,0,6,0,0,6,0,0],
];

$enemy = [
    [0,6,0,0,0,6,0],
    [6,6,6,0,6,6,6],
    [0,6,6,6,6,6,0],
    [0,0,6,6,6,0,0],
    [0,6,0,0,0,6,0],
];

$coin = [
    [0,5,5,5,0],
    [5,4,5,4,5],
    [5,5,5,5,5],
    [5,4,5,4,5],
    [0,5,5,5,0],
];

$stars = [];
for ($i = 0; $i < 54; $i++) {
    $stars[] = JAS::pixel('game_star_' . $i)
        ->at(($i * 61) % 790, 58 + (($i * 47) % 330))
        ->color($i % 4 === 0 ? '#7dd3fc' : '#f7f7f2')
        ->style([
            'width' => ($i % 2 + 1) . 'px',
            'height' => ($i % 2 + 1) . 'px',
        ]);
}

$enemies = [];
for ($i = 0; $i < 4; $i++) {
    $enemies[] = gamePixelBlock('enemy_' . $i, $enemy, $palette, 9)->attr('data-enemy', 'true');
}

$stage = JAS::stageBox('bit16_game', 800, 450)
    ->attr('data-asjah-game', 'bit16')
    ->children([
        JAS::scene('game_scene')->children([
            JAS::rectangle('sky_back')->at(0, 0)->size(800, 450)->fill('#0f172a'),
            ...$stars,
            JAS::rectangle('hud_bar')->at(0, 0)->size(800, 52)->fill('#111827'),
            JAS::sprite('game_title')->text('JAH 16-BIT FLIGHT')->at(22, 13)->style([
                'font-family' => 'monospace',
                'font-weight' => '800',
                'font-size' => '20px',
                'color' => '#7dd3fc',
                'text-shadow' => '3px 3px 0 #1d4ed8',
            ]),
            JAS::sprite('score_label')->text('SCORE')->at(480, 13)->style(['font-family' => 'monospace', 'font-weight' => '800', 'color' => '#f7f7f2']),
            JAS::sprite('score_value')->text('0')->at(558, 13)->style(['font-family' => 'monospace', 'font-weight' => '800', 'color' => '#ffcc33']),
            JAS::sprite('lives_label')->text('LIVES')->at(635, 13)->style(['font-family' => 'monospace', 'font-weight' => '800', 'color' => '#f7f7f2']),
            JAS::sprite('lives_value')->text('3')->at(715, 13)->style(['font-family' => 'monospace', 'font-weight' => '800', 'color' => '#22c55e']),
            JAS::sprite('game_message')->text('Flechas o WASD para moverte')->at(240, 410)->style([
                'font-family' => 'monospace',
                'font-size' => '14px',
                'color' => '#cbd5e1',
            ]),
            JAS::rectangle('ground')->at(0, 390)->size(800, 60)->fill('#1b1f4a'),
            JAS::rectangle('neon_line')->at(0, 388)->size(800, 4)->fill('#00ffcc'),
            gamePixelBlock('hero_ship', $ship, $palette, 10),
            gamePixelBlock('coin', $coin, $palette, 8)->style(['animation' => 'coinSpin 900ms steps(4) infinite']),
            ...$enemies,
        ]),
    ]);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JAS 16-bit Game</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #080b16; color: #f7f7f2; font-family: monospace; }
        .asjah-stage { border: 4px solid #334155; background: #0f172a; box-shadow: 0 0 0 4px #020617, 0 24px 80px #000; image-rendering: pixelated; }
        .asjah-scene { position: relative; width: 100%; height: 100%; overflow: hidden; }
        .asjah-pixel { box-sizing: border-box; }
        #hero_ship.hit { filter: brightness(2); }
        @keyframes coinSpin {
          0% { transform: scaleX(1); }
          50% { transform: scaleX(.25); }
          100% { transform: scaleX(1); }
        }
    </style>
    <script src="/actionscript-php-jah/assets/asjah-game16.js" defer></script>
</head>
<body>
    <?= $stage->render(); ?>
</body>
</html>
