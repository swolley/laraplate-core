<?php

declare(strict_types=1);

return [
    'name' => 'Core',

    /**
     * optimistic locking table column.
     */
    'locking' => [
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'lock_version_column' => env('LOCKIN_LOCK_VERSION_COLUMN', 'lock_version'),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'lock_at_column' => env('LOCKIN_LOCK_AT_COLUMN', 'locked_at'),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'lock_by_column' => env('LOCKIN_LOCK_BY_COLUMN', 'locked_user_id'),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'unlock_allowed' => env('LOCKIN_UNLOCK_ALLOWED', true),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'can_be_unlocked' => explode(',', (string) env('LOCKING_CAN_BE_UNLOCKED', '')),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'prevent_modifications_on_locked_objects' => env('LOCKING_PREVENT_MODIFICATIONS_ON_LOCKED', false),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'prevent_notifications_to_locked_objects' => env('LOCKING_PREVENT_MODIFICATIONS_TO_LOCKED', false),
    ],

    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'dynamic_entities' => env('ENABLE_DYNAMIC_ENTITIES', false),
    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'dynamic_gridutils' => env('ENABLE_DYNAMIC_GRIDUTILS', false),
    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'expose_crud_api' => env('EXPOSE_CRUD_API', false),
    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'enable_user_licenses' => env('ENABLE_USER_LICENSE', false),
    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'force_https' => env('FORCE_HTTPS', false),
    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'soft_deletes_expiration_days' => env('SOFT_DELETES_EXPIRATION_DAYS'),

    'extended_class_suffix' => '_extended',

    'editor' => env('APP_EDITOR', 'VSCode'),

    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'translation_fallback_enabled' => env('TRANSLATION_FALLBACK_ENABLED', true),

    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'auto_translate_enabled' => env('AUTO_TRANSLATE_ENABLED', false),
    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'auto_translate_provider' => env('AUTO_TRANSLATE_PROVIDER', 'deepl'),
    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'auto_translate_fallback_to_ai' => env('AUTO_TRANSLATE_FALLBACK_TO_AI', true),
    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'deepl_api_key' => env('DEEPL_API_KEY'),
    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'translation_cache_enabled' => env('TRANSLATION_CACHE_ENABLED', true),

    /**
     * Notification settings for pending approvals.
     * Sends notifications to admins when records are waiting for moderation.
     */
    'notifications' => [
        'approvals' => [
            // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
            'enabled' => env('APPROVAL_NOTIFICATIONS_ENABLED', true),
            // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
            'channels' => explode(',', (string) env('APPROVAL_NOTIFICATION_CHANNELS', 'mail')),
            // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
            'default_threshold_hours' => (int) env('APPROVAL_DEFAULT_THRESHOLD', 8),
            'recipients' => [
                'roles' => ['admin', 'superadmin'],
            ],
        ],
    ],
];
