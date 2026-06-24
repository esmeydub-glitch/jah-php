<?php

require_once __DIR__ . '/../app/ActionPHP/bootstrap.php';

$button = (new JahButton('Reproducir video', 'btn_play'))
    ->onClick('jah.video.play')
    ->salkProtect([
        'event' => 'jah.video.play',
        'process' => 'video.runtime',
        'payload' => ['file' => 'jah-demo.webm'],
    ]);

$html = $button->render();

foreach ([
    'data-jah-event="jah.video.play"',
    'data-jah-component="btn_play"',
    'data-salk-token=',
] as $expected) {
    if (!str_contains($html, $expected)) {
        fwrite(STDERR, "missing {$expected}\n");
        exit(1);
    }
}

echo "ActionPHP SALK component tests PASS\n";
