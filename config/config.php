<?php

declare(strict_types=1);

return [
    'name' => 'Core',

    /**
     * Trusted connection overrides for Core models that may be stored outside
     * the application's default database.
     *
     * Frozen: native modules share one schema on one connection. Add no
     * entries. See docs/database-connection-affinity-audit.md.
     *
     * @var array<class-string<Illuminate\Database\Eloquent\Model>, string>
     */
    'model_connections' => [],

    /**
     * Per-minute rate limits for Core-defined job queues (see RouteServiceProvider).
     * `embeddings` throttles calls to the embedding service; keep it low when the
     * service saturates under load, raise it for faster bulk backfills.
     */
    'queue_rate_limits' => [
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'embeddings' => (int) env('EMBEDDINGS_QUEUE_RATE_PER_MINUTE', 10),
    ],

    /**
     * Ceiling for how many models a bulk (scout:import) reindex writes to the
     * search engine per request. The adaptive batcher starts here and shrinks
     * when the engine strains, so this only caps the optimistic batch size.
     */
    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'bulk_index_batch' => (int) env('BULK_INDEX_BATCH', 100),

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
        'lock_until_column' => env('LOCKIN_LOCK_UNTIL_COLUMN', 'locked_until'),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'lease_ttl' => (int) env('LOCKING_LEASE_TTL', 900),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'unlock_allowed' => env('LOCKIN_UNLOCK_ALLOWED', true),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'can_be_unlocked' => explode(',', (string) env('LOCKING_CAN_BE_UNLOCKED', '')),
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'prevent_modifications_on_locked_objects' => env('LOCKING_PREVENT_MODIFICATIONS_ON_LOCKED', true),
    ],

    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'force_https' => env('FORCE_HTTPS', false),

    /*
     * How near an expiry has to be for HasValidity's `expiring` scope to report a
     * record, when the caller names no window of its own. Hours, because the right
     * answer differs by model: a task expires in days, a licence in months, so a
     * model can override it with `protected static int $expiring_within_hours`.
     */
    'validity' => [
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'expiring_within_hours' => (int) env('CORE_VALIDITY_EXPIRING_WITHIN_HOURS', 48),
    ],

    'extended_class_suffix' => '_extended',

    'editor' => env('APP_EDITOR', 'VSCode'),

    'filament' => [
        'tabs_counts_ttl_seconds' => env('FILAMENT_TABS_COUNTS_TTL_SECONDS', 60),
    ],

    'media' => [
        /**
         * Time-to-live, in hours, for pending media drafts (the token-keyed bucket
         * that backs CREATE forms). Drafts older than this are pruned, together with
         * their staged media, by the core:prune-media-drafts command.
         */
        // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
        'draft_ttl_hours' => (int) env('CORE_MEDIA_DRAFT_TTL_HOURS', 24),
    ],

    'acl' => [
        /**
         * Maximum number of users for targeted ACL cache invalidation.
         * When the number of users with a given permission exceeds this threshold,
         * the service falls back to flushing all ACL-prefixed cache keys instead
         * of deleting individual user-scoped entries.
         */
        'clear_threshold' => 500,
    ],

    'cache' => [
        /**
         * When true, the cache warming command (cache:warm) is executed automatically
         * during application boot via $app->booted(), after all service providers
         * have been registered. Useful for reducing cold-cache penalties after deployment.
         *
         * Defaults to false to avoid unexpected DB queries on every boot.
         */
        'warm_on_boot' => false,
    ],

    // @phpstan-ignore larastan.noEnvCallsOutsideOfConfig
    'deepl_api_key' => env('DEEPL_API_KEY'),

    /**
     * Notification settings for pending approvals.
     * Sends notifications to admins when records are waiting for moderation.
     */
    'notifications' => [
        'approvals' => [
            'recipients' => [
                'roles' => ['admin', 'superadmin'],
            ],
        ],
    ],
];
