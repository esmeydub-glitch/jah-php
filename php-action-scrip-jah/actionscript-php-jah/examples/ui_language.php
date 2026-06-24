<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Jah\ActionScript\JAS;

$table = JAS::table('events_table')
    ->headers(['ID', 'Proceso', 'Estado', 'Accion'])
    ->rows([
        ['1', 'video.runtime', 'firmado', JAS::button('Ver')->on('click', 'jah.event.inspect', ['id' => 1])],
        ['2', 'panel.runtime', 'firmado', JAS::button('Ver')->on('click', 'jah.event.inspect', ['id' => 2])],
    ]);

$form = JAS::form('process_form', [
    'action' => '/public/action_event.php',
    'method' => 'POST',
])->children([
    JAS::input('process', 'Proceso', '', ['required' => true]),
    JAS::select('mode', [
        'observe' => 'Observar',
        'execute' => 'Ejecutar',
        'lockdown' => 'Lockdown',
    ], 'Modo'),
    JAS::button('Firmar proceso')->on('click', 'jah.process.sign', [
        'source' => 'ui_language',
    ]),
]);

$app = JAS::stage('language_stage')->children([
    JAS::layout('main_layout', ['mode' => 'dashboard'])->children([
        JAS::panel('runtime_panel', ['title' => 'Runtime JAH ActionScript PHP'])->children([
            JAS::grid('stats_grid', ['columns' => 'repeat(auto-fit, minmax(180px, 1fr))'])->children([
                JAS::card('Stage', 1)->status('ok'),
                JAS::card('Scenes', 1)->status('ok'),
                JAS::card('Eventos SALK', 3)->status('ok'),
                JAS::card('Componentes', 9)->status('ok'),
            ]),
            $table,
        ]),
        JAS::panel('control_panel', ['title' => 'Control de proceso'])->children([
            $form,
        ]),
        JAS::modal('runtime_modal', ['title' => 'Modal firmado'])->open(false)->children([
            JAS::text('Este modal es parte del arbol logico renderizado por PHP puro.'),
        ]),
    ]),
]);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>JAH ActionScript PHP UI</title>
    <style>
        body { margin: 0; background: #10141f; color: #f7f7f2; font-family: system-ui, sans-serif; }
        .asjah-stage { min-height: 100vh; padding: 32px; box-sizing: border-box; }
        .asjah-layout, .asjah-panel-body, .asjah-form { display: grid; gap: 16px; }
        .asjah-layout-dashboard { max-width: 1180px; margin: 0 auto; }
        .asjah-panel, .asjah-card, .asjah-modal-content { border: 1px solid #334155; background: #172033; }
        .asjah-panel-header, .asjah-panel-body, .asjah-card, .asjah-modal-content { padding: 16px; }
        .asjah-panel-header { border-bottom: 1px solid #334155; }
        .asjah-panel-header h2, .asjah-card h3, .asjah-card p { margin: 0; }
        .asjah-card strong { display: block; color: #7dd3fc; font-size: 2rem; margin-top: 8px; }
        .asjah-grid { display: grid; grid-template-columns: var(--asjah-grid-columns, repeat(2, 1fr)); gap: 12px; }
        .asjah-table { width: 100%; border-collapse: collapse; }
        .asjah-table th, .asjah-table td { border-bottom: 1px solid #334155; padding: 10px; text-align: left; }
        .asjah-field { display: grid; gap: 6px; }
        .asjah-field input, .asjah-field select { border: 1px solid #334155; background: #0f172a; color: #f7f7f2; padding: 10px; }
        .asjah-button { width: fit-content; border: 1px solid #7dd3fc; background: #172033; color: #f7f7f2; padding: 10px 14px; cursor: pointer; }
        .asjah-modal[data-open="false"] { display: none; }
    </style>
    <script src="/actionscript-php-jah/assets/asjah-runtime.js" defer></script>
</head>
<body>
    <?= $app->render(); ?>
</body>
</html>
