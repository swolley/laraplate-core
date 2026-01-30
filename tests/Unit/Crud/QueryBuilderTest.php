<?php

declare(strict_types=1);

use Modules\Core\Services\Crud\QueryBuilder;

it('has proper class structure', function (): void {
    $reflection = new ReflectionClass(QueryBuilder::class);

    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('prepareQuery'))->toBeTrue();
});

it('has prepareQuery method with correct signature', function (): void {
    $reflection = new ReflectionClass(QueryBuilder::class);
    $method = $reflection->getMethod('prepareQuery');

    expect($method->isPublic())->toBeTrue();
    expect($method->getNumberOfParameters())->toBe(2);
});

it('has applyFilters method', function (): void {
    $reflection = new ReflectionClass(QueryBuilder::class);

    expect($reflection->hasMethod('applyFilters'))->toBeTrue();

    $method = $reflection->getMethod('applyFilters');
    expect($method->isPublic())->toBeTrue();
});

it('has proper namespace', function (): void {
    $reflection = new ReflectionClass(QueryBuilder::class);

    expect($reflection->getName())->toBe('Modules\Core\Services\Crud\QueryBuilder');
});

it('has proper class structure for query building', function (): void {
    $reflection = new ReflectionClass(QueryBuilder::class);

    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('prepareQuery'))->toBeTrue();
    expect($reflection->hasMethod('applyFilters'))->toBeTrue();
});
