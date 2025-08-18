<?php

declare(strict_types=1);

return [
    'default' => env('AI_PROVIDER', 'ollama'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            // 'openai_api_url' => env('OPENAI_API_URL'),
            'openai_model' => env('OPENAI_MODEL', null),
        ],

        'ollama' => [
            'api_url' => env('OLLAMA_API_URL'),
            'model' => env('OLLAMA_MODEL', 'nomic-embed-large'),
        ],

        'voyageai' => [
            'api_key' => env('VOYAGEAI_API_KEY'),
            'model' => env('VOYAGEAI_MODEL', 'voyage-3-lite'),
        ],

        'mistral' => [
            'api_key' => env('MISTRAL_API_KEY'),
            'model' => env('MISTRAL_MODEL', 'mistral-large-latest'),
        ],

        'sentence-transformers' => [
            'url' => env('SENTENCE_TRANSFORMERS_URL'),
            'api_key' => env('SENTENCE_TRANSFORMERS_API_KEY'),
        ],
    ],
];
