<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\Services\ElasticsearchService;

final class ElasticsearchServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // Load custom Elasticsearch configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/elastic.php',
            'elastic',
        );

        // Load Elasticsearch client configuration (if not already loaded by the package)
        // if (! $this->app['config']->has('elastic.connections')) {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/elastic.client.php',
            'elastic',
        );
        // }

        // Register the service as singleton
        $this->app->singleton('elasticsearch', ElasticsearchService::getInstance(...));

        // Create an alias for easier access to the service
        $this->app->alias('elasticsearch', ElasticsearchService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../../config/elastic.php' => config_path('elastic.php'),
            __DIR__ . '/../config/elastic.client.php' => config_path('elastic.client.php'),
        ], 'elasticsearch-config');
    }
}
