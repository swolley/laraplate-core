<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Events\MigrationsEnded;

class CommandListenerProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    #[\Override]
    public function register(): void {}

    /**
     * Get the services provided by the provider.
     */
    #[\Override]
    public function provides(): array
    {
        return [];
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen(MigrationsEnded::class, function () {
            info("Cleaning Inspected entities");
            Cache::tags(['inspector'])->flush();
        });
    }
}
