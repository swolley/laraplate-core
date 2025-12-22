<?php

declare(strict_types=1);

return [
    'default' => env('ELASTIC_CONNECTION', 'default'),
    'connections' => [
        'default' => array_filter([
            'hosts' => [
                env('ELASTIC_HOST', 'localhost:9200'),
            ],
            'retries' => (int) env('ELASTIC_RETRIES', 3),
            'sslVerification' => env('ELASTIC_SSL_VERIFICATION', true),
            'httpClientOptions' => [
                'timeout' => (int) env('ELASTIC_TIMEOUT', 60),
            ],
            // Include basicAuthentication only if username is set
            'basicAuthentication' => (env('ELASTIC_USERNAME') !== null && env('ELASTIC_USERNAME') !== '')
                ? [env('ELASTIC_USERNAME'), env('ELASTIC_PASSWORD', '')]
                : null,
            // Include apiKey only if api_key is set
            'apiKey' => (env('ELASTIC_API_KEY') !== null && env('ELASTIC_API_KEY') !== '')
                ? [env('ELASTIC_API_KEY'), env('ELASTIC_API_KEY_ID', '')]
                : null,
        ], static fn ($value) => $value !== null),
    ],
    'retry_on_conflict' => env('ELASTIC_RETRY_ON_CONFLICT', 3),
    'bulk_size' => env('ELASTIC_BULK_SIZE', 500),
];
