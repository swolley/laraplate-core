<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Override;

final class CommandListenerProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    #[Override]
    public function register(): void {}

    /**
     * Get the services provided by the provider.
     */
    #[Override]
    public function provides(): array
    {
        return [];
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // clears the cache og the inspected entities
        Event::listen(MigrationsEnded::class, function (): void {
            info('Cleaning Inspected entities');
            Cache::tags(Cache::getCacheTags('inspector'))->flush();
        });
    }
}
