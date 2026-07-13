<?php

declare(strict_types=1);

use Modules\Core\Providers\SearchServiceProvider;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Engines\DatabaseEngine;
use Modules\Core\Search\Services\AdvancedSearchService;
use Laravel\Scout\EngineManager;


beforeEach(function (): void {
    $this->provider = new SearchServiceProvider(app());
});

it('registers ISearchEngine singleton and search alias', function (): void {
    $this->provider->register();

    expect(app()->bound(ISearchEngine::class))->toBeTrue();
    expect(app()->bound('search'))->toBeTrue();
});

it('registers advanced search coordinator', function (): void {
    $this->provider->register();

    expect(app()->bound(AdvancedSearchService::class))->toBeTrue();
});

it('binds Scout engine implementations', function (): void {
    expect($this->provider->bindings)->toHaveKey(Elastic\ScoutDriverPlus\Engine::class);
    expect($this->provider->bindings)->toHaveKey(Laravel\Scout\Engines\TypesenseEngine::class);
});

it('registers the Core database search engine implementation with Scout', function (): void {
    config()->set('scout.driver', 'database');

    $this->provider->register();
    app(EngineManager::class)->forgetEngines();

    expect(app(EngineManager::class)->engine())->toBeInstanceOf(DatabaseEngine::class);
});
