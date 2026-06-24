<?php

declare(strict_types=1);

$html = shell_exec('php ' . escapeshellarg(__DIR__ . '/../examples/animacion/jah_skycore64.php'));

if (!is_string($html)) {
    fwrite(STDERR, "skycore64 pure php did not render\n");
    exit(1);
}

foreach ([
    'JAH SkyCore 64 PHP Puro',
    'method="post"',
    'name="state"',
    'name="action" value="shoot"',
    'NEXUS-NULL 64',
    'Estado firmado por SALK',
] as $expected) {
    if (!str_contains($html, $expected)) {
        fwrite(STDERR, "missing {$expected}\n");
        exit(1);
    }
}

foreach ([
    '<script',
    '</script>',
    '.js',
    'data-asjah-game',
] as $forbidden) {
    if (str_contains($html, $forbidden)) {
        fwrite(STDERR, "forbidden runtime marker found: {$forbidden}\n");
        exit(1);
    }
}

if (!preg_match('/name="state" value="([^"]+)"/', $html, $matches)) {
    fwrite(STDERR, "missing signed state token\n");
    exit(1);
}

$cmd = sprintf(
    'php %s',
    escapeshellarg(__DIR__ . '/../examples/animacion/jah_skycore64.php')
);

$descriptorSpec = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];

$process = proc_open($cmd, $descriptorSpec, $pipes, null, null);
if (!is_resource($process)) {
    fwrite(STDERR, "could not start post simulation\n");
    exit(1);
}

fwrite($pipes[0], '');
fclose($pipes[0]);
$postedHtml = stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);

if (!is_string($postedHtml) || !str_contains($postedHtml, 'JAH SkyCore 64 PHP Puro')) {
    fwrite(STDERR, "post simulation render failed\n");
    exit(1);
}

echo "JAH SkyCore 64 pure PHP smoke PASS\n";
