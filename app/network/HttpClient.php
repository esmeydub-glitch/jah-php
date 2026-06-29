<?php

declare(strict_types=1);

namespace Jah\Network;

/**
 * HttpClient — Cliente HTTP básico basado en cURL para peticiones GET/POST/PUT/DELETE.
 */
class HttpClient
{
    private int $timeout;
    private bool $verifySsl;

    public function __construct(int $timeout = 30, bool $verifySsl = true)
    {
        $this->timeout = $timeout;
        $this->verifySsl = $verifySsl;
    }

    /**
     * Ejecuta una petición HTTP.
     *
     * @param string $method Método HTTP (GET, POST, PUT, DELETE, etc.)
     * @param string $url URL destino
     * @param array $data Datos a enviar (como array o codificado según content-type)
     * @param array $headers Cabeceras personalizadas de la petición
     * @return array [code, body, headers]
     */
    public function request(string $method, string $url, array $data = [], array $headers = []): array
    {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true) || !$this->isAllowedUrl($url)) {
            throw new \InvalidArgumentException('Invalid HTTP method or URL.');
        }

        $ch = curl_init();
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize cURL.');
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $this->timeout));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);
        
        // Cabecera de respuesta
        curl_setopt($ch, CURLOPT_HEADER, true);

        // Estructurar cabeceras de envío
        $formattedHeaders = [];
        foreach ($headers as $key => $value) {
            $formattedHeaders[] = "{$key}: {$value}";
        }

        // Definir método e inyectar payload
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                $this->attachPayload($ch, $data, $formattedHeaders);
                break;
            case 'PUT':
            case 'DELETE':
            case 'PATCH':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                $this->attachPayload($ch, $data, $formattedHeaders);
                break;
            case 'GET':
            default:
                if (!empty($data)) {
                    $url = $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($data);
                    curl_setopt($ch, CURLOPT_URL, $url);
                }
                break;
        }

        if (!empty($formattedHeaders)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $formattedHeaders);
        }

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("cURL error: " . $error);
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Separar cabeceras y cuerpo de la respuesta
        $headerPart = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $parsedHeaders = $this->parseHeaders($headerPart);

        return [
            'code'    => $httpCode,
            'body'    => $body,
            'headers' => $parsedHeaders,
        ];
    }

    /**
     * Adjunta el cuerpo a la petición cURL según el tipo de datos proporcionado.
     */
    private function attachPayload($ch, array $data, array &$headers): void
    {
        $body = http_build_query($data);

        if (!$this->hasHeader($headers, 'Content-Type')) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    private function parseHeaders(string $headerRaw): array
    {
        $headers = [];
        $lines = explode("\r\n", trim($headerRaw));
        
        foreach ($lines as $line) {
            if (empty($line) || !str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = explode(':', $line, 2);
            $headers[trim($key)] = trim($value);
        }
        
        return $headers;
    }

    private function hasHeader(array $headers, string $name): bool
    {
        foreach ($headers as $header) {
            if (stripos($header, $name . ':') === 0) {
                return true;
            }
        }

        return false;
    }

    private function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);
        return in_array($scheme, ['http', 'https'], true);
    }
}
