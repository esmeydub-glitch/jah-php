<?php

declare(strict_types=1);

namespace Jah\Http;

use Jah\Security\SalkGuard;
use RuntimeException;

/**
 * JsonTransport
 *
 * Única capa autorizada para JSON público en JAH MemoryAgent.
 *
 * Regla:
 * - JSON se permite solo para transporte HTTP/API y Qwen Cloud.
 * - La API key nunca viaja dentro del JSON.
 * - Las acciones internas siguen en ActionScript PHP.
 * - La configuración interna sigue en PHP arrays.
 */
final class JsonTransport
{
    public static function decodeRequest(int $maxBytes = 1048576): array
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method !== 'POST') {
            return $_GET;
        }

        $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
        if (!str_contains($contentType, 'application/json')) {
            return $_POST;
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        if (strlen($raw) > $maxBytes) {
            throw new RuntimeException('JSON payload exceeds maximum allowed size');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid JSON payload');
        }

        return $decoded;
    }

    public static function encodePublic(array $payload, ?SalkGuard $salk = null, string $context = 'json.public'): string
    {
        if ($salk !== null) {
            $check = $salk->validatePublicJsonPayload($payload, $context);
            if (($check['ok'] ?? false) === false) {
                http_response_code(500);
                $payload = [
                    'status' => 'error',
                    'error' => 'SALK blocked public JSON payload because it may contain sensitive data',
                    'salk' => $check,
                ];
            }

            $payload = $salk->maskSecrets($payload);
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode public JSON response');
        }

        return $json;
    }

    public static function respond(array $payload, ?SalkGuard $salk = null, string $context = 'json.public', int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo self::encodePublic($payload, $salk, $context);
    }

    public static function encodeQwenPayload(array $payload): string
    {
        if (self::containsForbiddenQwenKeys($payload)) {
            throw new RuntimeException('Qwen JSON payload must not contain API keys, tokens or Authorization headers');
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode Qwen JSON payload');
        }

        return $json;
    }

    public static function decodeQwenResponse(string $response): array
    {
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function containsForbiddenQwenKeys(array $payload): bool
    {
        foreach ($payload as $key => $value) {
            $key = strtolower((string)$key);
            if (
                str_contains($key, 'api_key') ||
                str_contains($key, 'apikey') ||
                str_contains($key, 'authorization') ||
                str_contains($key, 'bearer') ||
                str_contains($key, 'token') ||
                str_contains($key, 'secret') ||
                str_contains($key, 'password')
            ) {
                return true;
            }

            if (is_array($value) && self::containsForbiddenQwenKeys($value)) {
                return true;
            }
        }

        return false;
    }
}
