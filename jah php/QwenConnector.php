<?php

declare(strict_types=1);

class QwenConnector
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, string $region = 'cn-hangzhou')
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = match ($region) {
            'us-west' => 'https://dashscope-us-west.aliyuncs.com/api/v1/services/aigc/text-generation/generation',
            'eu' => 'https://dashscope-eu.aliyuncs.com/api/v1/services/aigc/text-generation/generation',
            default => 'https://dashscope.aliyuncs.com/api/v1/services/aigc/text-generation/generation',
        };
    }

    public function chat(string $prompt, string $context = '', string $model = 'qwen-max'): string
    {
        $systemPrompt = $context !== ''
            ? "Eres un asistente útil con memoria. Usa el siguiente contexto si es relevante:\n{$context}"
            : 'Eres un asistente útil y preciso.';

        $data = [
            'model' => $model,
            'input' => [
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ],
            'parameters' => ['result_format' => 'message'],
        ];

        $jsonData = json_encode($data);
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ];

        if (function_exists('curl_init')) {
            return $this->callWithCurl($jsonData, $headers);
        }

        return $this->callWithStream($jsonData, $headers);
    }

    private function callWithCurl(string $jsonData, array $headers): string
    {
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            return "Error HTTP {$httpCode} conectando con Qwen";
        }

        return $this->parseResponse($response);
    }

    private function callWithStream(string $jsonData, array $headers): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $jsonData,
                'timeout' => 30,
            ],
        ]);

        $response = @file_get_contents($this->baseUrl, false, $context);

        if ($response === false) {
            $error = error_get_last();
            return 'Error conectando con Qwen: ' . ($error['message'] ?? 'desconocido');
        }

        return $this->parseResponse($response);
    }

    private function parseResponse(string $response): string
    {
        $result = json_decode($response, true);

        if (isset($result['code']) && $result['code'] !== '') {
            return "Error Qwen: {$result['message']}";
        }

        return $result['output']['choices'][0]['message']['content'] ?? 'Respuesta vacía de Qwen';
    }
}
