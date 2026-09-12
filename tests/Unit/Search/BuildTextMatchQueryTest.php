<?php

declare(strict_types=1);

use Laravel\Scout\Builder as ScoutBuilder;
use Modules\Core\Search\DTOs\TextMatchOptions;
use Modules\Core\Search\Engines\ElasticsearchEngine;
use Modules\Core\Search\Services\SearchQueryAnalyzer;
use Modules\Core\Search\Services\TextMatchOptionsResolver;
use Modules\Core\Tests\Stubs\Search\FlatTitleStubModel;
use Modules\Core\Tests\Stubs\Search\FullMappingStubModel;

it('targets per-locale title sub-fields for a model whose title is a locale object', function (): void {
    $resolver = new TextMatchOptionsResolver(new SearchQueryAnalyzer());
    $builder = new ScoutBuilder(new FullMappingStubModel(), 'festival');
    // Isolate the multi_match under test: skip the exact-match-boost bool/should wrapping.
    $builder->options[TextMatchOptionsResolver::BUILDER_OPTION] = ['exact_match_boost' => 0];
    $options = $resolver->forBuilder($builder);

    expect($options->fields)->toContain('title.*^2');

    $engine = (new ReflectionClass(ElasticsearchEngine::class))->newInstanceWithoutConstructor();
    $query = $engine->buildTextMatchQuery('festival', $options);

    expect($query)->toHaveKey('multi_match')
        ->and($query['multi_match']['fields'])->toContain('title.*^2');
});

it('keeps matching via the flat title field for a mono-language model', function (): void {
    $resolver = new TextMatchOptionsResolver(new SearchQueryAnalyzer());
    $builder = new ScoutBuilder(new FlatTitleStubModel(), 'festival');
    $builder->options[TextMatchOptionsResolver::BUILDER_OPTION] = ['exact_match_boost' => 0];
    $options = $resolver->forBuilder($builder);

    expect($options->fields)->toContain('title')
        ->and($options->fields)->toContain('*')
        ->and($options->fields)->not->toContain('title.*^2');

    $engine = (new ReflectionClass(ElasticsearchEngine::class))->newInstanceWithoutConstructor();
    $query = $engine->buildTextMatchQuery('festival', $options);

    expect($query['multi_match']['fields'])->toContain('title')
        ->and($query['multi_match']['fields'])->toContain('*');
});

it('falls back to a boosted title wildcard plus catch-all when the resolver has no explicit fields', function (): void {
    $engine = (new ReflectionClass(ElasticsearchEngine::class))->newInstanceWithoutConstructor();
    $query = $engine->buildTextMatchQuery('festival', TextMatchOptions::fromArray([
        'exact_match_boost' => 0,
    ]));

    expect($query['multi_match']['fields'])->toBe(['title.*^2', '*']);
});
