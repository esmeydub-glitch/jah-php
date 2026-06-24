<?php

declare(strict_types=1);

$html = shell_exec('php ' . escapeshellarg(__DIR__ . '/../examples/animacion/bit32_game.php'));

if (!is_string($html)) {
    fwrite(STDERR, "bit32 game did not render\n");
    exit(1);
}

foreach ([
    'data-asjah-game="bit32"',
    'id="hero32"',
    'data-enemy32="true"',
    'id="shots_layer"',
    'id="score32"',
    'id="energy32"',
    'asjah-game32.js',
] as $expected) {
    if (!str_contains($html, $expected)) {
        fwrite(STDERR, "missing {$expected}\n");
        exit(1);
    }
}

echo "JAS 32-bit game smoke PASS\n";
