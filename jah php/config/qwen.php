<?php

declare(strict_types=1);

return [
    'api_key' => getenv('QWEN_API_KEY') ?: '',
    'model' => getenv('QWEN_MODEL') ?: 'qwen-max',
    'base_url' => getenv('QWEN_BASE_URL') ?: 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
];
