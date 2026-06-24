<?php

declare(strict_types=1);

$html = shell_exec('php ' . escapeshellarg(__DIR__ . '/../examples/animacion/bit16_game.php'));

if (!is_string($html)) {
    fwrite(STDERR, "bit16 game did not render\n");
    exit(1);
}

foreach ([
    'data-asjah-game="bit16"',
    'id="hero_ship"',
    'id="coin"',
    'data-enemy="true"',
    'id="score_value"',
    'id="lives_value"',
    'asjah-game16.js',
] as $expected) {
    if (!str_contains($html, $expected)) {
        fwrite(STDERR, "missing {$expected}\n");
        exit(1);
    }
}

echo "JAS 16-bit game smoke PASS\n";
