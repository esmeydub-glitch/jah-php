<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use Jah\ActionScript\JAS;

$game = JAS::game32('jah_skycore')
    ->size(960, 540)
    ->fps(60)
    ->title('JAH SkyCore 32');

$game->player('salk01')
    ->at(80, 260)
    ->energy(100)
    ->speed(6)
    ->weapon('pulse');

$game->level('Boot Sector')
    ->background('grid_blue')
    ->enemies(10)
    ->shots(20)
    ->particles(50)
    ->boss('Gatekeeper')
    ->goal('destroy_boss');

$game->level('Data Highway')
    ->background('data_stream')
    ->enemies(20)
    ->shots(50)
    ->particles(100)
    ->hazards('data_packets')
    ->boss('Router Phantom');

$game->level('Memory Storm')
    ->background('ram_fog')
    ->enemies(40)
    ->shots(100)
    ->particles(300)
    ->boss('Cache Beast');

$game->level('GPU Core')
    ->background('neon_gpu')
    ->enemies(80)
    ->shots(200)
    ->particles(500)
    ->boss('Shader Dragon');

$game->level('SALK Lockdown')
    ->background('red_lockdown')
    ->enemies(120)
    ->shots(300)
    ->particles(800)
    ->objective('activate_4_seals')
    ->boss('NEXUS-NULL');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JAH SkyCore 32</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #020617; color: #f7f7f2; font-family: system-ui, sans-serif; }
        .jas-game32 { position: relative; overflow: hidden; border: 1px solid #475569; background: #050816; box-shadow: 0 28px 120px rgba(56,189,248,.24); }
        .game32-bg, .game32-world, .game32-hud, .game32-message { position: absolute; inset: 0; }
        .game32-world { overflow: hidden; }
        .layer-back { background: radial-gradient(circle at 70% 20%, rgba(56,189,248,.2), transparent 34%), #050816; }
        .layer-mid { background-image: linear-gradient(rgba(125,211,252,.08) 1px, transparent 1px), linear-gradient(90deg, rgba(125,211,252,.08) 1px, transparent 1px); background-size: 34px 34px; animation: gridMove 8s linear infinite; }
        .layer-front { pointer-events: none; box-shadow: inset 0 0 80px rgba(0,0,0,.7); }
        [data-bg="red_lockdown"] .layer-back { background: radial-gradient(circle at 70% 20%, rgba(239,68,68,.28), transparent 34%), #12050a; }
        [data-bg="neon_gpu"] .layer-back { background: radial-gradient(circle at 65% 30%, rgba(168,85,247,.28), transparent 34%), #050816; }
        [data-bg="ram_fog"] .layer-back { background: radial-gradient(circle at 35% 60%, rgba(34,197,94,.22), transparent 38%), #07130d; }
        [data-bg="data_stream"] .layer-back { background: radial-gradient(circle at 55% 25%, rgba(14,165,233,.25), transparent 34%), #06111f; }
        .game32-hud { height: 58px; display: flex; align-items: center; gap: 22px; padding: 0 18px; background: rgba(15,23,42,.86); z-index: 20; box-sizing: border-box; }
        .game-title { color: #7dd3fc; margin-right: auto; text-shadow: 0 0 14px #38bdf8; }
        .game32-message { top: auto; height: 28px; bottom: 10px; text-align: center; color: #cbd5e1; z-index: 22; }
        .game32-start { position: absolute; inset: 58px 0 0; display: grid; place-items: center; gap: 10px; align-content: center; background: rgba(2,6,23,.62); z-index: 40; }
        .game32-start button { border: 1px solid #7dd3fc; background: #172033; color: #f7f7f2; padding: 14px 18px; font-weight: 900; cursor: pointer; box-shadow: 0 0 20px rgba(56,189,248,.35); }
        .game32-start span { color: #cbd5e1; }
        .player32 { position: absolute; width: 76px; height: 42px; clip-path: polygon(0 50%, 25% 8%, 78% 0, 100% 50%, 78% 100%, 25% 92%); background: linear-gradient(135deg, #7dd3fc, #2563eb 48%, #f7f7f2 58%, #22c55e); box-shadow: 0 0 18px #38bdf8; will-change: transform; }
        .player32.hit32 { filter: brightness(2.3); }
        .sky-enemy32 { position: absolute; width: 46px; height: 34px; clip-path: polygon(0 15%, 82% 0, 100% 50%, 82% 100%, 0 85%, 28% 50%); background: linear-gradient(135deg, #ef4444, #7f1d1d 55%, #f97316); box-shadow: 0 0 12px rgba(239,68,68,.75); will-change: transform; }
        .sky-boss32 { position: absolute; display: grid; place-items: center; width: 140px; height: 82px; border: 1px solid #ef4444; background: linear-gradient(135deg, #450a0a, #991b1b); color: #fca5a5; font-weight: 900; font-size: 12px; text-align: center; box-shadow: 0 0 24px rgba(239,68,68,.65); will-change: transform; }
        .sky-shot32 { position: absolute; width: 24px; height: 5px; border-radius: 99px; background: #facc15; box-shadow: 0 0 12px #facc15; will-change: transform; }
        .salk-token32 { position: absolute; width: 28px; height: 28px; border-radius: 8px; background: linear-gradient(135deg, #22c55e, #7dd3fc); box-shadow: 0 0 18px rgba(34,197,94,.75); will-change: transform; }
        .particle32 { position: absolute; width: 2px; height: 2px; background: #7dd3fc; opacity: .75; will-change: transform; }
        @keyframes gridMove { from { background-position: 0 0; } to { background-position: -136px 0; } }
    </style>
    <script src="/actionscript-php-jah/assets/jah-skycore32.js" defer></script>
</head>
<body>
    <?= $game->render(); ?>
</body>
</html>
