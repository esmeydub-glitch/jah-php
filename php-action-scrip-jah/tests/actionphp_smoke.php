<?php

require_once __DIR__ . '/../app/ActionPHP/bootstrap.php';

$stage = new JahStage('main');
$scene = new JahScene('video_scene');
$button = new JahButton('Ejecutar flujo JAH');
$button->onClick('jah.flow.execute');
$video = new JahVideo('/media/jah-demo.webm', 'intro_video');
$video->controls(true)->preload('metadata');
$scene->add($button);
$scene->add($video);
$stage->add($scene);
$html = $stage->render();

if (!str_contains($html, 'jah-stage')) {
    fwrite(STDERR, "missing stage\n");
    exit(1);
}
if (!str_contains($html, 'data-jah-event="jah.flow.execute"')) {
    fwrite(STDERR, "missing event\n");
    exit(1);
}
if (!str_contains($html, '<video') || !str_contains($html, 'src="/media/jah-demo.webm"')) {
    fwrite(STDERR, "missing video\n");
    exit(1);
}

$dashboard = new JahDashboard('actionphp_runtime');
$dashboard->addCard('Stage', 'Activo', 'Escenario renderizado desde PHP')->status('ok');

$panel = new JahPanel('media_runtime');
$panel->title('Runtime multimedia');

$table = new JahTable('timeline_events');
$table->headers(['Frame', 'Objeto', 'Evento']);
$table->row(['1', 'Stage', (new JahButton('Cargar'))->onClick('jah.scene.load')]);

$form = new JahForm('video_event_form');
$form->action('/jah/video/event.php')->method('POST');
$form->add((new JahInput('clip', 'Clip'))->required());
$form->add(new JahSelect('action', [
    'play' => 'Play',
    'pause' => 'Pause',
], 'Accion'));
$form->add((new JahButton('Enviar evento'))->onClick('jah.video.event'));

$panel->add($table);
$panel->add($form);
$dashboard->addPanel($panel);
$dashboardHtml = $dashboard->render();

foreach ([
    'jah-dashboard',
    'jah-card',
    'jah-panel',
    'jah-table',
    'jah-form',
    'name="clip"',
    'data-jah-event="jah.video.event"',
] as $expected) {
    if (!str_contains($dashboardHtml, $expected)) {
        fwrite(STDERR, "missing {$expected}\n");
        exit(1);
    }
}

echo "ActionPHP smoke PASS\n";
