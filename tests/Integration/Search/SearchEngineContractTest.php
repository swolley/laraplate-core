<?php

declare(strict_types=1);

use Laravel\Scout\Builder as ScoutBuilder;
use Laravel\Scout\Engines\Engine as ScoutEngine;
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
    [DatabaseEngine::class, false],
]);
