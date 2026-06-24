<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Jah\\ActionScript\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

$salkToken = dirname(__DIR__, 2) . '/jah php/security/JahSalkToken.php';
if (is_file($salkToken)) {
    require_once $salkToken;
}
