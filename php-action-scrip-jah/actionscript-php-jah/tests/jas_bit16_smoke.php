<?php

declare(strict_types=1);

$html = shell_exec('php ' . escapeshellarg(__DIR__ . '/../examples/animacion/bit16.php'));

if (!is_string($html)) {
    fwrite(STDERR, "bit16 example did not render\n");
    exit(1);
}

foreach ([
    '16-BIT JAH',
    'id="hero_ship"',
    'id="coin"',
    '@keyframes',
] as $expected) {
    if (!str_contains($html, $expected)) {
        fwrite(STDERR, "missing {$expected}\n");
        exit(1);
    }
}

echo "JAS 16-bit smoke PASS\n";
