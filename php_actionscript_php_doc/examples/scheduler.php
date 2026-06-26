<?php

require_once __DIR__ . '/../ActionScriptEngine.php';
require_once __DIR__ . '/../JahEngineJas.php';

use Jah\JahEngineJas;

$engine = new JahEngineJas();
$engine->loadString(<<<'JAS'
policy("balanced")
observe(30s)
workers(2, 8)
require("status_ok", "===", true)
JAS);

$context = ['status_ok' => true];
$isValid = $engine->evaluate('balanced', $context);

echo $isValid ? "Policy OK" : "Policy FAIL";
