<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Jah\ActionScript\JAS;

$app = JAS::stage('test_stage')->children([
    JAS::scene('test_scene')->children([
        JAS::video('/public/media/jah-demo.webm', 'test_video'),
        JAS::button('Play', 'play_btn')
            ->attr('data-video-target', 'test_video')
            ->on('click', 'jah.video.play', [
                'file' => 'public/media/jah-demo.webm',
            ]),
    ]),
]);

$html = $app->render();

foreach ([
    'asjah-stage',
    'asjah-scene',
    '<video',
    'src="/public/media/jah-demo.webm"',
    'data-as-event="click"',
    'data-jah-event="jah.video.play"',
    'data-jah-component="play_btn"',
    'data-video-target="test_video"',
    'data-salk-token=',
] as $expected) {
    if (!str_contains($html, $expected)) {
        fwrite(STDERR, "missing {$expected}\n");
        exit(1);
    }
}

echo "JAH ActionScript PHP smoke PASS\n";

$ui = JAS::stage('ui_stage')->children([
    JAS::layout('layout')->children([
        JAS::panel('panel', ['title' => 'Panel'])->children([
            JAS::grid('grid')->children([
                JAS::card('Card', 1),
            ]),
            JAS::table('table')->headers(['A'])->row(['B']),
            JAS::form('form')->children([
                JAS::input('name', 'Name'),
                JAS::select('mode', ['a' => 'A'], 'Mode'),
            ]),
            JAS::modal('modal', ['title' => 'Modal'])->open(false),
        ]),
    ]),
])->render();

foreach ([
    'asjah-layout',
    'asjah-panel',
    'asjah-card',
    'asjah-grid',
    'asjah-table',
    'asjah-form',
    'asjah-field',
    '<select',
    'asjah-modal',
] as $expected) {
    if (!str_contains($ui, $expected)) {
        fwrite(STDERR, "missing ui {$expected}\n");
        exit(1);
    }
}

echo "JAH ActionScript PHP UI smoke PASS\n";
