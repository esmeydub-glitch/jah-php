<?php

require_once __DIR__ . '/../app/ActionPHP/bootstrap.php';

$stage = new JahStage('jah_flashless_player');

$scene = new JahScene('video_scene');

$intro = new JahSprite('intro_copy');
$intro->class('jah-hero-copy');
$intro->attr('role', 'group');
$intro->attr('aria-label', 'JAH ActionPHP');

$player = new JahSprite('jah_player_shell');
$player->class('jah-player-shell');

$video = new JahVideo('/media/jah-demo.webm', 'jah_intro_video');
$video->controls(true)->preload('metadata');

$controls = new JahSprite('player_controls');
$controls->class('jah-player-controls');
$controls->add((new JahButton('Cargar escena'))->onClick('jah.scene.load'));
$controls->add(
    (new JahButton('Reproducir video'))
        ->onClick('jah.video.play')
        ->salkProtect([
            'event' => 'jah.video.play',
            'process' => 'video.runtime',
            'payload' => ['file' => 'jah-demo.webm'],
        ])
);
$controls->add(
    (new JahButton('Pausar'))
        ->onClick('jah.video.pause')
        ->salkProtect([
            'event' => 'jah.video.pause',
            'process' => 'video.runtime',
            'payload' => ['file' => 'jah-demo.webm'],
        ])
);

$player->add($video);
$player->add($controls);

$timeline = new JahPanel('timeline');
$timeline->title('Timeline ActionPHP');
$timeline->add((new JahCard('Frame 1', 'Stage'))->subtitle('Escena creada desde PHP'));
$timeline->add((new JahCard('Frame 2', 'Video'))->subtitle('HTML5 reproduce, JAH coordina'));
$timeline->add((new JahCard('Frame 3', 'Evento'))->subtitle('data-jah-event conecta el motor'));

$scene->add($intro);
$scene->add($player);
$scene->add($timeline);
$stage->add($scene);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JAH ActionPHP Video</title>
    <link rel="stylesheet" href="/assets/css/jah-actionphp.css">
</head>
<body>
    <?= $stage->render(); ?>
</body>
</html>
