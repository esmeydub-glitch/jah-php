<?php

declare(strict_types=1);

$html = shell_exec('php ' . escapeshellarg(__DIR__ . '/../examples/animacion/jah_skycore32.php'));

if (!is_string($html)) {
    fwrite(STDERR, "skycore did not render\n");
    exit(1);
}

foreach ([
    'JAH SkyCore 32',
    'data-asjah-game="skycore32"',
    'data-salk-token=',
    'Boot Sector',
    'NEXUS-NULL',
    'data-game-start',
    'jah-skycore32.js',
] as $expected) {
    if (!str_contains($html, $expected)) {
        fwrite(STDERR, "missing {$expected}\n");
        exit(1);
    }
}

if (!preg_match('/<script type="application\/json" class="jas-game32-config">(.*?)<\/script>/s', $html, $matches)) {
    fwrite(STDERR, "missing game32 json manifest\n");
    exit(1);
}

$manifest = json_decode($matches[1], true);
if (!is_array($manifest) || count($manifest['levels'] ?? []) !== 5) {
    fwrite(STDERR, "invalid game32 json manifest\n");
    exit(1);
}

echo "JAH SkyCore 32 smoke PASS\n";
