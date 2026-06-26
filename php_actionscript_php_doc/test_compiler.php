<?php

require_once __DIR__ . '/JasBinaryCompiler.php';
require_once __DIR__ . '/JasNativeCompiler.php';

echo "=== Compiler Test ===\n";

$binary = Jah\JasBinaryCompiler::compileExit(42);
echo "Binary size: " . strlen($binary) . "\n";

$source = "<?php echo 'hello';";
$valid = Jah\JasNativeCompiler::validate($source);
echo "Native validation: " . ($valid ? "OK" : "FAIL") . "\n";