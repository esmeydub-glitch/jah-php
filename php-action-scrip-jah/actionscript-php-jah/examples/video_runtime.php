<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Jah\ActionScript\JAS;

$app = JAS::stage('jah_app')->children([
    JAS::scene('intro')->children([
        JAS::sprite('copy')->children([
            JAS::text('JAH ActionScript PHP'),
            JAS::text('PHP puro escrito como codigo visual logico: Stage, Scene, Sprite, Video y eventos firmados.'),
        ]),
        JAS::sprite('player')->children([
            JAS::video('/public/media/jah-demo.webm', 'intro_video'),
            JAS::sprite('controls')->children([
                JAS::button('Reproducir', 'play_btn')
                    ->attr('data-video-target', 'intro_video')
                    ->on('click', 'jah.video.play', [
                        'file' => 'public/media/jah-demo.webm',
                    ]),
                JAS::button('Pausar', 'pause_btn')
                    ->attr('data-video-target', 'intro_video')
                    ->on('click', 'jah.video.pause', [
                        'file' => 'public/media/jah-demo.webm',
                    ]),
            ]),
        ]),
    ]),
]);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JAH ActionScript PHP</title>
    <style>
        body { margin: 0; background: #10141f; color: #f7f7f2; font-family: system-ui, sans-serif; }
        .asjah-stage { min-height: 100vh; display: grid; place-items: center; padding: 32px; box-sizing: border-box; }
        .asjah-scene { width: min(1040px, 100%); display: grid; gap: 20px; }
        .asjah-sprite { display: grid; gap: 12px; }
        #copy .asjah-text:first-child { margin: 0; color: #7dd3fc; font-size: clamp(2rem, 5vw, 4rem); font-weight: 800; }
        #copy .asjah-text { margin: 0; color: #cbd5e1; font-size: 1.1rem; }
        #player { border: 1px solid #334155; background: #050816; padding: 16px; }
        .asjah-video { width: 100%; aspect-ratio: 16 / 9; background: #020617; }
        #controls { display: flex; flex-wrap: wrap; gap: 10px; }
        .asjah-button { border: 1px solid #7dd3fc; background: #172033; color: #f7f7f2; padding: 10px 14px; cursor: pointer; }
    </style>
    <script src="/actionscript-php-jah/assets/asjah-runtime.js" defer></script>
</head>
<body>
    <?= $app->render(); ?>
</body>
</html>
