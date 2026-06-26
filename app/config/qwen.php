<?php

declare(strict_types=1);

require_once __DIR__ . '/environment.php';

return [
    'api_key' => (string) jah_env('QWEN_API_KEY', ''),
    'model' => (string) jah_env('QWEN_MODEL', 'qwen-max'),
    'base_url' => (string) jah_env('QWEN_BASE_URL', 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'),
];
