<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\Setting;
use Override;

final class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        \Modules\Core\Events\ModelRequiresIndexing::class => [
            \Modules\Core\Listeners\IndexModelFallbackListener::class, // Executes after AI listeners
        ],
        \Modules\Core\Events\ModelPreProcessingCompleted::class => [
            \Modules\Core\Listeners\FinalizeModelIndexingListener::class, // Handles finalization
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    #[Override]
    public function boot(): void
    {
        Event::listen([
            'eloquent.saved: ' . CronJob::class,
            'eloquent.deleted: ' . CronJob::class,
        ], function (CronJob $cronJob): void {
            Cache::forget($cronJob->getTable());
        });

        Event::listen([
            'eloquent.saved: ' . Setting::class,
            'eloquent.deleted: ' . Setting::class,
        ], function (Setting $setting): void {
            Cache::tags(Cache::getCacheTags($setting->getTable()))->flush();
        });
    }

    /**
     * Configure the proper event listeners for email verification.
     */
    #[Override]
    protected function configureEmailVerification(): void
    {
        //
    }
}
