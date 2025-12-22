<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Custom Elasticsearch configurations
    |--------------------------------------------------------------------------
    |
    | This file contains only configurations not managed by the
    | babenkoivan/elastic-client and babenkoivan/elastic-scout-driver packages.
    | For client configurations use config/elastic.client.php
    | For scout driver configurations use config/elastic.scout_driver.php
    |
    */

    'indices' => [
        // Default settings for indices
        'default_settings' => [
            'number_of_shards' => 1,
            'number_of_replicas' => 0,
            'analysis' => [
                'analyzer' => [
                    'italian_analyzer' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'italian_stemmer', 'italian_stop'],
                    ],
                ],
                'filter' => [
                    'italian_stemmer' => [
                        'type' => 'stemmer',
                        'language' => 'italian',
                    ],
                    'italian_stop' => [
                        'type' => 'stop',
                        'stopwords' => '_italian_',
                    ],
                ],
            ],
        ],

        // Index prefix
        'prefix' => env('ELASTIC_INDEX_PREFIX', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue configuration for Elasticsearch jobs
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'connection' => env('ELASTIC_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
        'queue' => env('ELASTIC_QUEUE', 'indexing'),
        'timeout' => env('ELASTIC_QUEUE_TIMEOUT', 300), // 5 minutes
        'tries' => env('ELASTIC_QUEUE_TRIES', 3),
        'backoff' => [30, 60, 120], // waiting time between retries in seconds
    ],
];
