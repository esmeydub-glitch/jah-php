<?php

require_once __DIR__ . '/../app/ActionPHP/bootstrap.php';

$stage = new JahStage('main');
$scene = new JahScene('dashboard');
$button = new JahButton('Ejecutar flujo JAH');
$button->onClick('jah.flow.execute');
$video = new JahVideo('/media/jah-demo.webm');

$scene->add($button);
$scene->add($video);
$stage->add($scene);

echo $stage->render();
