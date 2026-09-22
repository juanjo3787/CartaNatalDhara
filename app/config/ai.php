<?php

return [
    'enabled' => (bool) env('AI_ENABLED', false),
    'provider' => env('AI_PROVIDER', 'openai'),
    'api_key' => env('AI_API_KEY'),
    'model' => env('AI_MODEL', 'gpt-4o-mini'),
    'temperature' => (float) env('AI_TEMPERATURE', 0.7),
    'base_url' => env('AI_BASE_URL', 'https://api.openai.com/v1'),
    'timeout' => (int) env('AI_TIMEOUT', 25),
    'connect_timeout' => (int) env('AI_CONNECT_TIMEOUT', 10),
    'verify_ssl' => (bool) env('AI_VERIFY_SSL', true),
    'currency' => env('AI_CURRENCY', 'EUR'),
    'usd_to_eur' => (float) env('AI_USD_TO_EUR', 0.92),
    'tax_rate' => (float) env('AI_TAX_RATE', 21),
    'pricing' => [
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4.1-mini' => ['input' => 0.40, 'output' => 1.60],
        'gpt-5-mini' => ['input' => 0.25, 'output' => 2.00],
    ],
];
