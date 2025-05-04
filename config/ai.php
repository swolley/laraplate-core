<?php

declare(strict_types=1);

return [
    'openai_api_key' => env('OPENAI_API_KEY'),
    'openai_api_url' => env('OPENAI_API_URL'),
    'openai_model' => env('OPENAI_MODEL', null),

    'ollama_api_url' => env('OLLAMA_API_URL'),
    'ollama_model' => env('OLLAMA_MODEL', 'llama3.2:3b'),
];
