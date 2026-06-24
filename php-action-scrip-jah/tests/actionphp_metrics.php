<?php

require_once __DIR__ . '/../app/ActionPHP/bootstrap.php';

$root = dirname(__DIR__);
$videoPath = $root . '/public/media/jah-demo.webm';

$startedAt = hrtime(true);

$stage = new JahStage('metrics_stage');
$scene = new JahScene('metrics_scene');
$video = new JahVideo('/media/jah-demo.webm', 'metrics_video');
$video->controls(true)->preload('metadata');

$scene->add($video);
$scene->add((new JahButton('Reproducir video'))->onClick('jah.video.play'));
$scene->add((new JahButton('Pausar'))->onClick('jah.video.pause'));
$stage->add($scene);

$html = $stage->render();
$renderMs = (hrtime(true) - $startedAt) / 1_000_000;

$metrics = [
    'php_version' => PHP_VERSION,
    'render_ms' => round($renderMs, 3),
    'html_bytes' => strlen($html),
    'component_count' => [
        'stage' => substr_count($html, 'jah-stage'),
        'scene' => substr_count($html, 'jah-scene'),
        'video' => substr_count($html, '<video'),
        'button' => substr_count($html, '<button'),
        'jah_events' => substr_count($html, 'data-jah-event='),
        'salk_tokens' => substr_count($html, 'data-salk-token='),
    ],
    'video' => [
        'path' => 'public/media/jah-demo.webm',
        'exists' => is_file($videoPath),
        'bytes' => is_file($videoPath) ? filesize($videoPath) : 0,
        'readable' => is_readable($videoPath),
    ],
];

$httpMetrics = collectHttpMetrics('http://127.0.0.1:8002/demo_dashboard.php');
$videoHttpMetrics = collectHttpMetrics('http://127.0.0.1:8002/media/jah-demo.webm');

if ($httpMetrics !== null) {
    $metrics['http_demo'] = $httpMetrics;
}

if ($videoHttpMetrics !== null) {
    $metrics['http_video'] = $videoHttpMetrics;
}

echo json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (!$metrics['video']['exists'] || !$metrics['video']['readable']) {
    fwrite(STDERR, "video asset missing or unreadable\n");
    exit(1);
}

if ($metrics['component_count']['video'] !== 1 || $metrics['component_count']['jah_events'] < 2) {
    fwrite(STDERR, "rendered component metrics are incomplete\n");
    exit(1);
}

function collectHttpMetrics(string $url): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS => 1500,
    ]);

    curl_exec($handle);

    if (curl_errno($handle) !== 0) {
        curl_close($handle);
        return null;
    }

    $metrics = [
        'status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
        'content_type' => curl_getinfo($handle, CURLINFO_CONTENT_TYPE) ?: '',
        'content_length' => curl_getinfo($handle, CURLINFO_CONTENT_LENGTH_DOWNLOAD),
        'total_ms' => round(curl_getinfo($handle, CURLINFO_TOTAL_TIME) * 1000, 3),
    ];

    curl_close($handle);
    return $metrics;
}
