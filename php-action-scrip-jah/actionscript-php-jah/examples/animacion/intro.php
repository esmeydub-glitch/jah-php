<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use Jah\ActionScript\JAS;

$logo = JAS::sprite('logo')
    ->text('JAH')
    ->at(50, 120)
    ->size(140, 70)
    ->style([
        'font-size' => '48px',
        'font-weight' => '800',
        'color' => '#7dd3fc',
    ]);

$ball = JAS::circle('ball')
    ->at(100, 280)
    ->radius(22)
    ->fill('#00ffcc');

$bar = JAS::rectangle('bar')
    ->at(80, 360)
    ->size(140, 10)
    ->fill('#334155');

$timeline = JAS::timeline('intro_timeline')
    ->add(
        JAS::tween('logo')
            ->from(['x' => 50, 'y' => 120, 'opacity' => 0, 'scale' => 0.5])
            ->to(['x' => 350, 'y' => 120, 'opacity' => 1, 'scale' => 1])
            ->duration(1200)
            ->ease('ease-out')
            ->onFinish('animation.logo.finished')
    )
    ->add(
        JAS::tween('ball')
            ->from(['x' => 100, 'y' => 280])
            ->to(['x' => 650, 'y' => 280])
            ->duration(2000)
            ->ease('linear')
            ->onFinish('animation.ball.finished')
    )
    ->add(
        JAS::tween('bar')
            ->from(['width' => 140, 'opacity' => 0.4])
            ->to(['width' => 620, 'opacity' => 1])
            ->duration(1800)
            ->ease('ease-in-out')
    );

$stage = JAS::stageBox('animation_demo', 800, 450)->children([
    JAS::scene('main')->children([
        $logo,
        $ball,
        $bar,
        JAS::line('path')->from(100, 305)->to(675, 305)->stroke('#1f9fb5')->width(2),
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
    <title>JAS Animacion</title>
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #10141f; color: #f7f7f2; font-family: system-ui, sans-serif; }
        .asjah-stage { border: 1px solid #334155; background: #050816; }
        .asjah-scene { position: relative; width: 100%; height: 100%; }
        .asjah-sprite { display: grid; place-items: center; }
        .asjah-line { position: absolute; inset: 0; pointer-events: none; }
    </style>
    <?= $compiled->styleTag(); ?>
</head>
<body>
    <?= $compiled->html(); ?>
</body>
</html>
