<?php

declare(strict_types=1);

return [
    'duration' => [
        'short' => env('CACHE_DURATION_SHORT', 10),
        'medium' => env('CACHE_DURATION_MEDIUM', 300),
        'long' => env('CACHE_DURATION_LONG', 3600),
    ],
];
