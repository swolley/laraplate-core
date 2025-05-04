<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Laravel\Scout\EngineManager;
use Elastic\ScoutDriverPlus\Engine;
use Illuminate\Support\ServiceProvider;
use Typesense\Client as TypesenseClient;
// use Modules\Core\Services\ElasticsearchService;
use Modules\Core\Search\Engines\ElasticsearchEngine;
// use Elastic\Elasticsearch\Client as ElasticsearchClient;
use Modules\Core\Search\Contracts\SearchEngineInterface;

/**
 * Extended search service provider
 * Adds support for vector search and custom features on top of Laravel Scout.
 */
final class SearchServiceProvider extends ServiceProvider
{
    public array $bindings = [
        Engine::class => ElasticsearchEngine::class,
    ];

    /**
     * Register services in the container.
     */
    public function register(): void
    {
        // Register Elasticsearch client
        // $this->app->singleton(ElasticsearchClient::class, function ($app) {
        //     return ElasticsearchService::getInstance()->getClient();
        // });

        // Register Typesense client
        $this->app->singleton('typesense', function ($app) {
            $config = $app['config']['scout.typesense.client-settings'];

            return new TypesenseClient($config);
        });

        // Register the search engine interface for backward compatibility
        $this->app->singleton(SearchEngineInterface::class, function ($app) {
            return $app->make(EngineManager::class)->engine();
        });

        // Create an alias for easier access
        $this->app->alias(SearchEngineInterface::class, 'search');
    }
}
