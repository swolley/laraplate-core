<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Cache\CacheManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Events\MigrationsEnded;

class CommandListenerProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    #[\Override]
    public function register(): void
    {
        //
    }

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
        Event::listen(MigrationsEnded::class, function (MigrationsEnded $event) {
            info("Cleaning Inspected entities");
            $this->app->make(CacheManager::class)->tags(['inspector'])->flush();
        });
    }
}
