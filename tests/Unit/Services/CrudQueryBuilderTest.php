<?php

declare(strict_types=1);

use Modules\Core\Casts\Column;
use Modules\Core\Casts\ColumnType;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('splits column name on last dot', function (): void {
    $builder = new QueryBuilder();
    $ref = new ReflectionClass(QueryBuilder::class);
    $method = $ref->getMethod('splitColumnNameOnLastDot');
    $method->setAccessible(true);

    $parts = $method->invoke($builder, 'orders.customer.name');

    expect($parts)->toBe(['orders.customer', 'name']);
});

it('groups main and relation columns correctly', function (): void {
    $qb = new QueryBuilder();
    $ref = new ReflectionClass(QueryBuilder::class);
    $method = $ref->getMethod('groupColumns');
    $method->setAccessible(true);

    $mainEntity = 'orders';
    $columns = [
        new Column('orders.id', ColumnType::COLUMN),
        new Column('orders.total', ColumnType::COLUMN),
        new Column('orders.customer.name', ColumnType::COLUMN),
    ];

    $args = [&$mainEntity, $columns];
    $result = $method->invokeArgs($qb, $args);

    expect($result['main'])->toHaveCount(2)
        ->and($result['relations'])->toHaveKey('customer');
});

it('cleans reserved recursive relations from relations list', function (): void {
    $qb = new QueryBuilder();
    $ref = new ReflectionClass(QueryBuilder::class);
    $method = $ref->getMethod('cleanRelations');
    $method->setAccessible(true);

    $relations = ['user', 'history', 'children', 'posts'];
    $args = [&$relations];
    $method->invokeArgs($qb, $args);

    sort($relations);
    expect(array_values($relations))->toBe(['posts', 'user']);
});
