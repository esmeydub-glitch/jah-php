<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use Jah\ActionScript\JAS;

function sprite32(string $id, string $kind): \Jah\ActionScript\Components\Sprite
{
    $sprite = JAS::sprite($id)->style([
        'position' => 'absolute',
        'left' => '0',
        'top' => '0',
        'will-change' => 'transform',
    ]);

    if ($kind === 'hero') {
        return $sprite->class('ship32')->style([
            'width' => '72px',
            'height' => '48px',
            'clip-path' => 'polygon(0 50%, 22% 12%, 72% 0, 100% 50%, 72% 100%, 22% 88%)',
            'background' => 'linear-gradient(135deg, #7dd3fc 0%, #2563eb 48%, #111827 50%, #f7f7f2 62%, #22c55e 100%)',
            'box-shadow' => '0 0 18px #38bdf8, inset -8px 0 0 rgba(255,255,255,.22)',
        ]);
    }

    return $sprite->class('enemy32')->attr('data-enemy32', 'true')->style([
        'width' => '58px',
        'height' => '42px',
        'clip-path' => 'polygon(0 15%, 82% 0, 100% 50%, 82% 100%, 0 85%, 28% 50%)',
        'background' => 'linear-gradient(135deg, #ef4444, #7f1d1d 55%, #f97316)',
        'box-shadow' => '0 0 14px rgba(239,68,68,.75), inset 6px 0 0 rgba(255,255,255,.18)',
    ]);
}

$stars = [];
for ($i = 0; $i < 80; $i++) {
    $stars[] = JAS::pixel('nebula_star_' . $i)
        ->at(($i * 73) % 798, 62 + (($i * 41) % 350))
        ->color($i % 5 === 0 ? '#38bdf8' : '#f7f7f2')
        ->style([
            'width' => ($i % 3 + 1) . 'px',
            'height' => ($i % 3 + 1) . 'px',
            'opacity' => (string) (0.35 + (($i % 5) * 0.12)),
        ]);
}

$enemies = [];
for ($i = 0; $i < 5; $i++) {
    $enemies[] = sprite32('enemy32_' . $i, 'enemy');
}

$stage = JAS::stageBox('bit32_game', 800, 450)
    ->attr('data-asjah-game', 'bit32')
    ->children([
        JAS::scene('bit32_scene')->children([
            JAS::rectangle('space_back32')->at(0, 0)->size(800, 450)->fill('#050816'),
            JAS::rectangle('nebula32')->at(0, 0)->size(800, 450)->fill('radial-gradient(circle at 70% 20%, rgba(56,189,248,.22), transparent 32%), radial-gradient(circle at 20% 80%, rgba(34,197,94,.16), transparent 28%), #050816'),
            ...$stars,
            JAS::rectangle('hud32')->at(0, 0)->size(800, 58)->fill('linear-gradient(90deg, rgba(15,23,42,.95), rgba(30,41,59,.85))'),
            JAS::sprite('title32')->text('JAH 32-BIT ORBIT')->at(22, 15)->style([
                'font-family' => 'system-ui, sans-serif',
                'font-weight' => '900',
                'font-size' => '21px',
                'letter-spacing' => '1px',
                'color' => '#f7f7f2',
                'text-shadow' => '0 0 14px #38bdf8',
            ]),
            JAS::sprite('score_label32')->text('SCORE')->at(455, 17)->style(['font-weight' => '800', 'color' => '#94a3b8']),
            JAS::sprite('score32')->text('0')->at(530, 17)->style(['font-weight' => '900', 'color' => '#facc15']),
            JAS::sprite('energy_label32')->text('ENERGY')->at(610, 17)->style(['font-weight' => '800', 'color' => '#94a3b8']),
            JAS::sprite('energy32')->text('100')->at(700, 17)->style(['font-weight' => '900', 'color' => '#22c55e']),
            JAS::sprite('message32')->text('WASD/Flechas mover - Espacio dispara')->at(220, 412)->style(['font-size' => '14px', 'color' => '#cbd5e1']),
            JAS::rectangle('floor32')->at(0, 390)->size(800, 60)->fill('linear-gradient(90deg, #111827, #1e293b, #111827)'),
            JAS::rectangle('laser_line32')->at(0, 386)->size(800, 3)->fill('#38bdf8'),
            JAS::sprite('shots_layer'),
            sprite32('hero32', 'hero'),
            ...$enemies,
        ]),
    ]);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JAS 32-bit Game</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #020617; color: #f7f7f2; font-family: system-ui, sans-serif; }
        .asjah-stage { border: 1px solid #475569; background: #050816; box-shadow: 0 28px 110px rgba(56,189,248,.22); }
        .asjah-scene { position: relative; width: 100%; height: 100%; overflow: hidden; }
        .asjah-sprite { position: absolute; }
        .shot32 { position: absolute; width: 22px; height: 5px; border-radius: 99px; background: #facc15; box-shadow: 0 0 12px #facc15; will-change: transform; }
        .hit32 { filter: brightness(2.2) saturate(1.5); }
        #shots_layer { position: absolute; inset: 0; pointer-events: none; }
    </style>
    <script src="/actionscript-php-jah/assets/asjah-game32.js" defer></script>
</head>
<body>
    <?= $stage->render(); ?>
</body>
</html>
