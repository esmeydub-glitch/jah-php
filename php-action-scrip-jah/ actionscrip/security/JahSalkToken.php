<?php

declare(strict_types=1);

namespace Jah\Security;

final class JahSalkToken
{
    public static function make(array $payload, ?int $ttl = null): string
    {
        $now = time();
        $payload['iat'] = $payload['iat'] ?? $now;
        $payload['expires'] = $payload['expires'] ?? ($now + ($ttl ?? 300));
        $payload['nonce'] = $payload['nonce'] ?? bin2hex(random_bytes(16));

        $json = self::canonicalJson($payload);
        $signature = hash_hmac('sha512', $json, self::secret());

        return self::base64UrlEncode($json) . '.' . $signature;
    }

    public static function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return ['ok' => false, 'error' => 'SALK_TOKEN_FORMAT_INVALID'];
        }

        [$encoded, $signature] = $parts;
        $json = self::base64UrlDecode($encoded);
        if ($json === false) {
            return ['ok' => false, 'error' => 'SALK_TOKEN_BASE64_INVALID'];
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return ['ok' => false, 'error' => 'SALK_TOKEN_JSON_INVALID'];
        }

        $expectedJson = self::canonicalJson($payload);
        $expectedSignature = hash_hmac('sha512', $expectedJson, self::secret());
        if (!hash_equals($expectedSignature, $signature)) {
            return ['ok' => false, 'error' => 'SALK_SIGNATURE_INVALID'];
        }

        if ((int) ($payload['expires'] ?? 0) < time()) {
            return ['ok' => false, 'error' => 'SALK_TOKEN_EXPIRED'];
        }

        return ['ok' => true, 'payload' => $payload];
    }

    public static function payloadHash(array $payload): string
    {
        return hash('sha256', self::canonicalJson($payload));
    }

    private static function canonicalJson(array $payload): string
    {
        self::sortRecursive($payload);
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException('SALK payload cannot be encoded.');
        }

        return $json;
    }

    private static function sortRecursive(array &$payload): void
    {
        ksort($payload);
        foreach ($payload as &$value) {
            if (is_array($value)) {
                self::sortRecursive($value);
            }
        }
    }

    private static function secret(): string
    {
        $secret = $_ENV['JAH_SALK_SECRET'] ?? $_SERVER['JAH_SALK_SECRET'] ?? getenv('JAH_SALK_SECRET');
        if (is_string($secret) && $secret !== '') {
            return $secret;
        }

        return hash('sha512', dirname(__DIR__) . '|jah-local-salk-secret');
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string|false
    {
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($data, '-_', '+/'), true);
    }
}
