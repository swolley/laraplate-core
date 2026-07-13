<?php

declare(strict_types=1);

use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Engines\Engine as ScoutEngine;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Engines\DatabaseEngine;
use Modules\Core\Search\Engines\ElasticsearchEngine;
use Modules\Core\Search\Engines\TypesenseEngine;

it('declares the scout search signature on the core search engine contract', function (): void {
    $method = new ReflectionMethod(ISearchEngine::class, 'search');
    $parameters = $method->getParameters();

    expect($parameters)->toHaveCount(1)
        ->and((string) $parameters[0]->getType())->toBe(ScoutBuilder::class)
        ->and((string) $method->getReturnType())->toBe('mixed');
});

it('declares orchestrated search support on the core search engine contract', function (): void {
    $method = new ReflectionMethod(ISearchEngine::class, 'supportsOrchestratedSearch');

    expect($method->getNumberOfRequiredParameters())->toBe(0)
        ->and((string) $method->getReturnType())->toBe('bool');
});

it('declares orchestrated vector search support on the core search engine contract', function (): void {
    $method = new ReflectionMethod(ISearchEngine::class, 'supportsOrchestratedVectorSearch');

    expect($method->getNumberOfRequiredParameters())->toBe(0)
        ->and((string) $method->getReturnType())->toBe('bool');
});

it('keeps core search engine createIndex signatures compatible with Laravel Scout', function (string $engine): void {
    $base = new ReflectionMethod(ScoutEngine::class, 'createIndex');
    $method = new ReflectionMethod($engine, 'createIndex');
    $name_parameter = $method->getParameters()[0];

    expect($name_parameter->getType())->not->toBeNull()
        ->and((string) $name_parameter->getType())->toBe('mixed')
        ->and($method->getNumberOfRequiredParameters())->toBe($base->getNumberOfRequiredParameters());
})->with([
    ElasticsearchEngine::class,
    TypesenseEngine::class,
    DatabaseEngine::class,
]);

it('exposes orchestrated search support per engine', function (string $engine, bool $expected): void {
    $method = new ReflectionMethod($engine, 'supportsOrchestratedSearch');
    $instance = $method->isStatic() ? null : (new ReflectionClass($engine))->newInstanceWithoutConstructor();

    expect($method->invoke($instance))->toBe($expected);
})->with([
    [ElasticsearchEngine::class, true],
    [TypesenseEngine::class, true],
    [DatabaseEngine::class, true],
]);

it('exposes orchestrated vector support per engine', function (string $engine, bool $expected): void {
    $method = new ReflectionMethod($engine, 'supportsOrchestratedVectorSearch');
    $instance = $method->isStatic() ? null : (new ReflectionClass($engine))->newInstanceWithoutConstructor();

    expect($method->invoke($instance))->toBe($expected);
})->with([
    [ElasticsearchEngine::class, true],
    [TypesenseEngine::class, true],
    [DatabaseEngine::class, true],
]);

it('translates advanced portable filters to elasticsearch query clauses', function (): void {
    $engine = (new ReflectionClass(ElasticsearchEngine::class))->newInstanceWithoutConstructor();

    $filters = $engine->buildSearchFilters([
        'operator' => 'and',
        'filters' => [
            ['field' => 'id', 'operator' => '>=', 'value' => 10],
            ['field' => 'id', 'operator' => '<', 'value' => 20],
            ['field' => 'created_at', 'operator' => 'between', 'value' => ['2024-01-01', '2024-01-31']],
            [
                'operator' => 'or',
                'filters' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 'draft'],
                    ['field' => 'status', 'operator' => '=', 'value' => 'published'],
                ],
            ],
        ],
    ]);

    expect($filters)->toMatchArray([
        [
            'bool' => [
                'must' => [
                    ['range' => ['id' => ['gte' => 10]]],
                    ['range' => ['id' => ['lt' => 20]]],
                    ['range' => ['created_at' => ['gte' => '2024-01-01', 'lte' => '2024-01-31']]],
                    [
                        'bool' => [
                            'should' => [
                                ['term' => ['status' => 'draft']],
                                ['term' => ['status' => 'published']],
                            ],
                            'minimum_should_match' => 1,
                        ],
                    ],
                ],
            ],
        ],
    ]);
});

it('translates advanced portable filters to typesense filter syntax', function (): void {
    $engine = (new ReflectionClass(TypesenseEngine::class))->newInstanceWithoutConstructor();

    $filters = $engine->buildSearchFilters([
        'operator' => 'and',
        'filters' => [
            ['field' => 'id', 'operator' => '>=', 'value' => 10],
            ['field' => 'id', 'operator' => '<', 'value' => 20],
            ['field' => 'created_at', 'operator' => 'between', 'value' => ['2024-01-01', '2024-01-31']],
            [
                'operator' => 'or',
                'filters' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 'draft'],
                    ['field' => 'status', 'operator' => '=', 'value' => 'published'],
                ],
            ],
        ],
    ]);

    expect($filters)->toBe('id:>=10 && id:<20 && created_at:>="2024-01-01" && created_at:<="2024-01-31" && (status:="draft" || status:="published")');
});

it('adds advanced portable filters to typesense keyword search parameters', function (): void {
    $engine = (new ReflectionClass(TypesenseEngine::class))->newInstanceWithoutConstructor();
    $model = new class extends Model
    {
        public function searchableAs(): string
        {
            return 'users';
        }
    };
    $builder = new ScoutBuilder($model, 'alpha');
    $builder->options['advanced_filters'] = [
        'operator' => 'and',
        'filters' => [
            ['field' => 'id', 'operator' => '>=', 'value' => 10],
            [
                'operator' => 'or',
                'filters' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 'draft'],
                    ['field' => 'status', 'operator' => '=', 'value' => 'published'],
                ],
            ],
        ],
    ];

    $parameters = $engine->buildSearchParameters($builder, 2, 15);

    expect($parameters['filter_by'])->toBe('id:>=10 && (status:="draft" || status:="published")')
        ->and($parameters['page'])->toBe(2)
        ->and($parameters['per_page'])->toBe(15);
});
