<?php

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Seeder;
use Illuminate\Console\Command;
use Modules\Core\Overrides\Seeder as CustomSeeder;
use Modules\Core\Overrides\Command as CustomCommand;
use Modules\Core\Overrides\ServiceProvider as CustomServiceProvider;

class OverridesServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Registra le classi personalizzate come binding per le classi native di Laravel
        // $this->app->bind(Seeder::class, CustomSeeder::class);
        // $this->app->bind(Command::class, CustomCommand::class);
        // $this->app->bind(ServiceProvider::class, CustomServiceProvider::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Qui puoi aggiungere logica di boot se necessario
    }
}
