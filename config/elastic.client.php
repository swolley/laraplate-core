<?php

declare(strict_types=1);

return [
    'default' => env('ELASTIC_CONNECTION', 'default'),
    'connections' => [
        'default' => [
            'hosts' => [
                env('ELASTIC_HOST', 'localhost:9200'),
            ],
            // Configurazione timeout e connessione
            'retries' => env('ELASTIC_RETRIES', 3),
            'timeout' => env('ELASTIC_TIMEOUT', 60),
            'connect_timeout' => env('ELASTIC_CONNECT_TIMEOUT', 10),
            'ssl_verification' => env('ELASTIC_SSL_VERIFICATION', true),

            // Autenticazione
            'username' => env('ELASTIC_USERNAME'),
            'password' => env('ELASTIC_PASSWORD'),

            // Logging
            'log_enabled' => env('ELASTIC_LOG_ENABLED', false),
            'log_level' => env('ELASTIC_LOG_LEVEL', 'error'),
        ],
    ],

    // Configurazione globale
    'retry_on_conflict' => env('ELASTIC_RETRY_ON_CONFLICT', 3),
    'bulk_size' => env('ELASTIC_BULK_SIZE', 500),
];
