<?php

declare(strict_types=1);

namespace Jah\Http;

use RuntimeException;

final class RequestGuard
{
    public static function authorize(array $config): void
    {
        self::assertSameOrigin();
        $expected = (string)($_ENV['JAH_API_KEY'] ?? getenv('JAH_API_KEY') ?: '');
        if ($expected !== '') {
            $provided = (string)($_SERVER['HTTP_X_JAH_API_KEY'] ?? '');
            $authorization = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
            if ($provided === '' && str_starts_with($authorization, 'Bearer ')) {
                $provided = substr($authorization, 7);
            }
            if ($provided === '' || !hash_equals($expected, $provided)) {
                throw new RuntimeException('Acceso API no autorizado');
            }
            return;
        }

        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if (PHP_SAPI !== 'cli' && !in_array($remote, ['127.0.0.1', '::1'], true)) {
            throw new RuntimeException('JAH_API_KEY es obligatoria para acceso remoto');
        }
    }

    public static function assertMethod(string $method, array $allowed): void
    {
        if (!in_array(strtoupper($method), $allowed, true)) {
            throw new RuntimeException('Método HTTP no permitido');
        }
    }

    public static function csrfToken(): string
    {
        self::startSession();
        if (!is_string($_SESSION['jah_csrf'] ?? null)) {
            $_SESSION['jah_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['jah_csrf'];
    }

    public static function assertCsrf(string $token): void
    {
        $expected = self::csrfToken();
        if ($token === '' || !hash_equals($expected, $token)) {
            throw new RuntimeException('Token CSRF inválido');
        }
    }

    public static function browserIsAuthorized(): bool
    {
        self::startSession();
        $expected = (string)($_ENV['JAH_API_KEY'] ?? getenv('JAH_API_KEY') ?: '');
        if ($expected !== '') return ($_SESSION['jah_authorized'] ?? false) === true;
        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        return PHP_SAPI === 'cli' || in_array($remote, ['127.0.0.1', '::1'], true);
    }

    public static function loginBrowser(string $provided): bool
    {
        self::startSession();
        $expected = (string)($_ENV['JAH_API_KEY'] ?? getenv('JAH_API_KEY') ?: '');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) return false;
        session_regenerate_id(true);
        $_SESSION['jah_authorized'] = true;
        return true;
    }

    public static function logoutBrowser(): void
    {
        self::startSession();
        $_SESSION = [];
        session_regenerate_id(true);
    }

    private static function assertSameOrigin(): void
    {
        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($origin === '' || $host === '') return;
        $originHost = parse_url($origin, PHP_URL_HOST);
        if (!is_string($originHost) || strtolower($originHost) !== strtolower(explode(':', $host, 2)[0])) {
            throw new RuntimeException('Origen de petición no permitido');
        }
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $sessionPath = (string)($_ENV['JAH_SESSION_PATH'] ?? getenv('JAH_SESSION_PATH') ?: (sys_get_temp_dir() . '/jah_sessions'));
        if (!is_dir($sessionPath) && !mkdir($sessionPath, 0700, true) && !is_dir($sessionPath)) {
            throw new RuntimeException('No se pudo crear el directorio de sesiones JAH');
        }
        session_save_path($sessionPath);
        session_name('JAHSESSID');
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
        ]);
    }
}
