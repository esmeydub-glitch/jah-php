<?php

return [
    'api_key' => $_ENV['QWEN_API_KEY'] ?? getenv('QWEN_API_KEY') ?? '',
    'model' => 'qwen-max',
];
