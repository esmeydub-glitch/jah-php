<?php

declare(strict_types=1);

use Jah\Http\JsonTransport;

class QwenConnector
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(string $apiKey, ?string $baseUrl = null)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($baseUrl ?: (getenv('QWEN_BASE_URL') ?: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'), '/');
    }

    public function chat(string $prompt, string $context = '', string $model = 'qwen-max'): string
    {
        if ($this->apiKey === '') {
            return 'Error: QWEN_API_KEY no configurada.';
        }

        if (!function_exists('curl_init')) {
            return 'Error: PHP cURL no está disponible. Qwen Cloud requiere cURL nativo de PHP.';
        }

        $systemPrompt = "You are Qwen, an AI assistant created by Alibaba Cloud. Always respond in Spanish. Use stored memory only when relevant. Never invent stored user preferences.";

        $userMessage = $prompt;
        if ($context !== '') {
            $userMessage = "<<<MEMORIA_RECUPERADA>>>{$context}\n<<<FIN_MEMORIA_RECUPERADA>>>\n\n<<<PREGUNTA>>>\n{$prompt}\n<<<FIN_PREGUNTA>>>\n\nResponde en español. Si la memoria recuperada contiene información relevante, úsala. Si no hay memoria suficiente, dilo con claridad.";
        }

        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
        ];

        try {
            $jsonPayload = JsonTransport::encodeQwenPayload($data);
        } catch (Throwable $e) {
            return 'Error: no se pudo preparar la petición segura para Qwen: ' . $e->getMessage();
        }

        $ch = curl_init($this->baseUrl . '/chat/completions');
        if ($ch === false) {
            return 'Error: no se pudo iniciar cURL.';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return 'Error cURL: ' . ($curlError !== '' ? $curlError : 'sin respuesta');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return "Error HTTP {$httpCode}: " . substr($response, 0, 500);
        }

        return $this->parseResponse($response);
    }

    private function parseResponse(string $response): string
    {
        $result = JsonTransport::decodeQwenResponse($response);
        if ($result === []) {
            return 'Respuesta no JSON de Qwen: ' . substr($response, 0, 300);
        }

        if (isset($result['choices'][0]['message']['content'])) {
            return (string)$result['choices'][0]['message']['content'];
        }

        if (isset($result['output']['choices'][0]['message']['content'])) {
            return (string)$result['output']['choices'][0]['message']['content'];
        }

        return 'Respuesta vacía de Qwen: ' . substr($response, 0, 300);
    }
}
