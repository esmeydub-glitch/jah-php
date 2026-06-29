<?php

declare(strict_types=1);

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
            throw new RuntimeException('QWEN_API_KEY no configurada.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL no está disponible. Qwen Cloud requiere cURL nativo de PHP.');
        }

        $systemPrompt = "Eres Qwen, un asistente de IA creado por Alibaba Cloud. Responde siempre en español y conversa con naturalidad. Usa la conversación Hot/Warm para entender referencias a turnos anteriores y no pidas al usuario información que ya aparece allí. Usa la memoria Cold solo cuando sea relevante. Nunca inventes preferencias ni recuerdos.";

        $userMessage = $prompt;
        if ($context !== '') {
            $userMessage = "<<<CONTEXTO_JAH>>>{$context}\n<<<FIN_CONTEXTO_JAH>>>\n\n<<<MENSAJE_ACTUAL>>>\n{$prompt}\n<<<FIN_MENSAJE_ACTUAL>>>\n\nResponde en español como continuación natural de la conversación. Si una referencia puede resolverse con el contexto JAH, hazlo directamente. Pide aclaración únicamente cuando existan varias interpretaciones reales.";
        }

        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
        ];

        if ($this->containsForbiddenPayloadKeys($data)) {
            throw new RuntimeException('Payload Qwen bloqueado por SALK: no se permiten secretos en el cuerpo.');
        }

        $jsonPayload = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($jsonPayload === false) {
            throw new RuntimeException('No se pudo preparar la petición para Qwen.');
        }

        $ch = curl_init($this->baseUrl . '/chat/completions');
        if ($ch === false) {
            throw new RuntimeException('No se pudo iniciar cURL.');
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
            throw new RuntimeException('Error cURL: ' . ($curlError !== '' ? $curlError : 'sin respuesta'));
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("Error HTTP {$httpCode} al consultar Qwen.");
        }

        return $this->parseResponse($response);
    }

    private function containsForbiddenPayloadKeys(array $payload): bool
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

            if (is_array($value) && $this->containsForbiddenPayloadKeys($value)) {
                return true;
            }
        }

        return false;
    }

    private function parseResponse(string $response): string
    {
        $result = json_decode($response, true);
        if (!is_array($result)) {
            throw new RuntimeException('Respuesta no interpretable de Qwen.');
        }

        if (isset($result['choices'][0]['message']['content'])) {
            return (string)$result['choices'][0]['message']['content'];
        }

        if (isset($result['output']['choices'][0]['message']['content'])) {
            return (string)$result['output']['choices'][0]['message']['content'];
        }

        throw new RuntimeException('Respuesta vacía de Qwen.');
    }
}
