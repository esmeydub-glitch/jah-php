<?php

declare(strict_types=1);

class QwenConnector
{
    private string $apiKey;
    private string $baseUrl = 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1';

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function chat(string $prompt, string $context = '', string $model = 'qwen-max'): string
    {
        $systemPrompt = !empty($context)
            ? "Eres un asistente útil y preciso. Usa este contexto:\n" . $context
            : 'Eres un asistente útil y preciso.';

        $data = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return "Error HTTP {$httpCode}: " . substr($response ?: 'Sin respuesta', 0, 300);
        }

        return $this->parseResponse($response);
    }

    private function parseResponse(string $response): string
    {
        $result = json_decode($response, true);

        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }

        if (isset($result['output']['choices'][0]['message']['content'])) {
            return $result['output']['choices'][0]['message']['content'];
        }

        return 'Respuesta vacía: ' . substr($response, 0, 200);
    }
}
