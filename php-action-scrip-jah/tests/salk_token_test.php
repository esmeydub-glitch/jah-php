<?php

require_once __DIR__ . '/../jah php/security/JahSalkToken.php';

use Jah\Security\JahSalkToken;

function fail_salk(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assert_salk(bool $condition, string $message): void
{
    if (!$condition) {
        fail_salk($message);
    }
}

$payload = [
    'purpose' => 'component_event',
    'event' => 'jah.video.play',
    'component_id' => 'btn_play',
    'payload_hash' => JahSalkToken::payloadHash(['file' => 'jah-demo.webm']),
];

$token = JahSalkToken::make($payload);
$valid = JahSalkToken::verify($token);
assert_salk($valid['ok'] === true, 'SALK token valido debe verificar.');

$tampered = substr($token, 0, -1) . (str_ends_with($token, 'a') ? 'b' : 'a');
$invalid = JahSalkToken::verify($tampered);
assert_salk($invalid['ok'] === false, 'SALK token alterado debe rechazarse.');

$expired = JahSalkToken::make($payload + ['expires' => time() - 1]);
$expiredResult = JahSalkToken::verify($expired);
assert_salk($expiredResult['ok'] === false && $expiredResult['error'] === 'SALK_TOKEN_EXPIRED', 'SALK token expirado debe rechazarse.');

echo "SALK token tests PASS\n";
