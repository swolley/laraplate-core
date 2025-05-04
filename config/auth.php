<?php

declare(strict_types=1);

return [
    'verify_new_user' => env('VERIFY_NEW_USER', false),
    'enable_user_registration' => env('ENABLE_USER_REGISTRATION', false),
    'enable_user_2fa' => env('ENABLE_USER_2FA', false),
    'enable_user_licenses' => env('ENABLE_USER_LICENSES', false),

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', Modules\Core\Models\User::class),
        ],
        'socialite' => [
            'enabled' => env('ENABLE_SOCIAL_LOGIN', false),
        ],
    ],
];
