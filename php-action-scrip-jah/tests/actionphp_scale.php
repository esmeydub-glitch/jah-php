<?php

require_once __DIR__ . '/../app/ActionPHP/bootstrap.php';

$options = getopt('', [
    'panels::',
    'buttons::',
    'tables::',
    'rows::',
    'events::',
]);

$panels = positiveInt($options['panels'] ?? 100);
$buttons = positiveInt($options['buttons'] ?? 1000);
$tables = positiveInt($options['tables'] ?? 100);
$rows = positiveInt($options['rows'] ?? 10);
$events = positiveInt($options['events'] ?? 1000);

$memoryStart = memory_get_usage(true);
$peakStart = memory_get_peak_usage(true);
$buildStarted = hrtime(true);

$stage = new JahStage('scale_stage');
$scene = new JahScene('scale_scene');

for ($i = 1; $i <= $panels; $i++) {
    $panel = new JahPanel('panel_' . $i);
    $panel->title('Panel ' . $i);
    $panel->add(new JahCard('Componentes', $buttons + $tables, 'Carga visual ActionPHP'));
    $scene->add($panel);
}

for ($i = 1; $i <= $buttons; $i++) {
    $scene->add((new JahButton('Evento ' . $i))->onClick('scale.event.' . $i));
}

for ($i = 1; $i <= $tables; $i++) {
    $table = new JahTable('table_' . $i);
    $table->headers(['Frame', 'Objeto', 'Evento']);
    for ($row = 1; $row <= $rows; $row++) {
        $table->row([
            (string) $row,
            'Sprite ' . $row,
            (new JahButton('Emitir'))->onClick('scale.table.' . $i . '.row.' . $row),
        ]);
    }
    $scene->add($table);
}

$stage->add($scene);

$buildMs = elapsedMs($buildStarted);
$renderStarted = hrtime(true);
$html = $stage->render();
$renderMs = elapsedMs($renderStarted);

$eventStarted = hrtime(true);
$eventNames = [];
for ($i = 1; $i <= $events; $i++) {
    $eventNames[] = 'scale.event.' . $i;
}
$eventBuildMs = elapsedMs($eventStarted);

$memoryEnd = memory_get_usage(true);
$peakEnd = memory_get_peak_usage(true);
$componentCount = [
    'stage' => substr_count($html, 'jah-stage'),
    'scene' => substr_count($html, 'jah-scene'),
    'panel' => preg_match_all('/<section[^>]+class="[^"]*\bjah-panel\b/', $html),
    'card' => substr_count($html, 'jah-card'),
    'table' => substr_count($html, '<table'),
    'button' => substr_count($html, '<button'),
    'jah_events' => substr_count($html, 'data-jah-event='),
    'salk_tokens' => substr_count($html, 'data-salk-token='),
];

$metrics = [
    'input' => [
        'panels' => $panels,
        'buttons' => $buttons,
        'tables' => $tables,
        'rows_per_table' => $rows,
        'synthetic_events' => $events,
    ],
    'timing_ms' => [
        'build_components' => round($buildMs, 3),
        'render_html' => round($renderMs, 3),
        'build_event_names' => round($eventBuildMs, 3),
    ],
    'memory_bytes' => [
        'start' => $memoryStart,
        'end' => $memoryEnd,
        'delta' => $memoryEnd - $memoryStart,
        'peak_delta' => $peakEnd - $peakStart,
    ],
    'html_bytes' => strlen($html),
    'component_count' => $componentCount,
];

echo json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if ($componentCount['button'] < $buttons || $componentCount['jah_events'] < $buttons) {
    fwrite(STDERR, "scale render did not include expected events\n");
    exit(1);
}

function positiveInt(mixed $value): int
{
    $int = filter_var($value, FILTER_VALIDATE_INT);
    if ($int === false || $int < 0) {
        fwrite(STDERR, "invalid numeric benchmark parameter\n");
        exit(1);
    }

    return $int;
}

function elapsedMs(int $startedAt): float
{
    return (hrtime(true) - $startedAt) / 1_000_000;
}
