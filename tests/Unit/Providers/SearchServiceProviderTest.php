<?php

declare(strict_types=1);

use Modules\Core\Providers\SearchServiceProvider;
use Modules\Core\Search\Contracts\ISearchEngine;


beforeEach(function (): void {
    $this->provider = new SearchServiceProvider(app());
});

it('registers ISearchEngine singleton and search alias', function (): void {
    $this->provider->register();

    expect(app()->bound(ISearchEngine::class))->toBeTrue();
    expect(app()->bound('search'))->toBeTrue();
});

it('binds Scout engine implementations', function (): void {
    expect($this->provider->bindings)->toHaveKey(Elastic\ScoutDriverPlus\Engine::class);
    expect($this->provider->bindings)->toHaveKey(Laravel\Scout\Engines\TypesenseEngine::class);
});
