<?php

declare(strict_types=1);

return [
    'openai_api_key' => env('OPENAI_API_KEY'),
    // 'openai_api_url' => env('OPENAI_API_URL'),
    'openai_model' => env('OPENAI_MODEL', null),

    'ollama_api_url' => env('OLLAMA_API_URL'),
    'ollama_model' => env('OLLAMA_MODEL', 'nomic-embed-large'),

    'voyageai_api_key' => env('VOYAGEAI_API_KEY'),
    'voyageai_model' => env('VOYAGEAI_MODEL', 'voyage-3-lite'),

    'mistral_api_key' => env('MISTRAL_API_KEY'),
    // 'mistral_model' => env('MISTRAL_MODEL', 'mistral-large-latest'),

    'sentence_transformers_url' => env('SENTENCE_TRANSFORMERS_URL'),
    'sentence_transformers_api_key' => env('SENTENCE_TRANSFORMERS_API_KEY'),
];
