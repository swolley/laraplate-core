<?php

declare(strict_types=1);

use Hedii\LaravelGelfLogger\Processors\RenameIdFieldProcessor;
// use Monolog\Formatter\GelfMessageFormatter;
use Modules\Core\Logging\GelfAdditionalInfoProcessor;
use Modules\Core\Logging\GelfLoggerFactory;

return [
    'channels' => [
        'gelf' => [
            'driver' => 'custom',
            'level' => env('GELF_LEVEL', 'error'),
            'via' => GelfLoggerFactory::class,
            'host' => env('GELF_URL'),
            'port' => env('GELF_PORT', 12201),
            // 'formatter' => GelfMessageFormatter::class,
            'processors' => [
                [
                    GelfAdditionalInfoProcessor::class,
                    RenameIdFieldProcessor::class,
                ],
            ],
        ],
    ],
];
