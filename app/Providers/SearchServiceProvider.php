<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Elastic\ScoutDriverPlus\Engine;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use Modules\Core\Search\Contracts\ISearchEngine;
// use Modules\Core\Services\ElasticsearchService;
use Modules\Core\Search\Engines\ElasticsearchEngine;
// use Elastic\Elasticsearch\Client as ElasticsearchClient;
use Typesense\Client as TypesenseClient;

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
        $this->app->singleton('typesense', function (array $app): TypesenseClient {
            $config = $app['config']['scout.typesense.client-settings'];

            return new TypesenseClient($config);
        });

        // Register the search engine interface for backward compatibility
        $this->app->singleton(ISearchEngine::class, fn ($app) => $app->make(EngineManager::class)->engine());

        // Create an alias for easier access
        $this->app->alias(ISearchEngine::class, 'search');
    }
}
