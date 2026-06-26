<?php

require_once __DIR__ . '/../ActionScriptEngine.php';

use Jah\Actions\ActionScript;

$action = ActionScript::define('math.double');
$action
    ->requires(['value'])
    ->timeout(100)
    ->handler(static fn(array $data): int => (int) $data['value'] * 2);

$result = ActionScript::run('math.double', ['value' => 21]);
echo "Result: " . print_r($result, true);
