<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Elastic\ScoutDriverPlus\Engine as BaseElasticsearchEngine;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\TypesenseEngine as BaseTypesenseEngine;
use Modules\Core\Search\Contracts\IQueryIntentParser;
use Modules\Core\Search\Contracts\IReranker;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Contracts\ISearchPlanner;
use Modules\Core\Search\Engines\ElasticsearchEngine;
use Modules\Core\Search\Engines\TypesenseEngine;
use Modules\Core\Search\Services\FallbackSearchPlanner;
use Modules\Core\Search\Services\HeuristicReranker;
use Modules\Core\Search\Services\SimpleQueryIntentParser;

/**
 * Extended search service provider.
 *
 * Adds support for vector search, ensemble retrieval, reranking,
 * and custom features on top of Laravel Scout.
 * Registers fallback implementations that the AI module can override.
 */
final class SearchServiceProvider extends ServiceProvider
{
    public array $bindings = [
        BaseElasticsearchEngine::class => ElasticsearchEngine::class,
        BaseTypesenseEngine::class => TypesenseEngine::class,
    ];

    /**
     * Register services in the container.
     */
    public function register(): void
    {
        $this->app->singleton(ISearchEngine::class, static fn (\Illuminate\Contracts\Foundation\Application $app) => $app->make(EngineManager::class)->engine());

        $this->app->alias(ISearchEngine::class, 'search');

        $this->app->singletonIf(IReranker::class, HeuristicReranker::class);
        $this->app->singletonIf(ISearchPlanner::class, FallbackSearchPlanner::class);
        $this->app->singletonIf(IQueryIntentParser::class, SimpleQueryIntentParser::class);
    }
}
