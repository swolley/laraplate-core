<?php

declare(strict_types=1);

use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\FiltersGroupCast;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\ACL;


it('hydrates group from json string and dehydrates back to json', function (): void {
    $cast = new FiltersGroupCast();
    $model = new ACL();
    $json = json_encode([
        'operator' => 'or',
        'filters' => [
            ['property' => 'name', 'value' => 'john', 'operator' => '='],
            ['filters' => [['property' => 'active', 'value' => true, 'operator' => '=']], 'operator' => 'and'],
        ],
    ], JSON_THROW_ON_ERROR);

    $group = $cast->get($model, 'filters', $json, []);
    $encoded = $cast->set($model, 'filters', $group, []);

    expect($group)->toBeInstanceOf(FiltersGroup::class)
        ->and($group->operator)->toBe(WhereClause::OR)
        ->and($group->filters[0])->toBeInstanceOf(Filter::class)
        ->and($group->filters[1])->toBeInstanceOf(FiltersGroup::class)
        ->and($encoded)->toBeString();
});

it('returns null on invalid get payloads and supports list payload hydration', function (): void {
    $cast = new FiltersGroupCast();
    $model = new ACL();

    $from_invalid_json = $cast->get($model, 'filters', '{invalid', []);
    $from_invalid_type = $cast->get($model, 'filters', 123, []);
    $from_list = $cast->get($model, 'filters', [
        ['property' => 'name', 'value' => 'john', 'operator' => '='],
    ], []);

    expect($from_invalid_json)->toBeNull()
        ->and($from_invalid_type)->toBeNull()
        ->and($from_list)->toBeInstanceOf(FiltersGroup::class);
});

it('accepts array payload in set and rejects invalid set values', function (): void {
    $cast = new FiltersGroupCast();
    $model = new ACL();

    $encoded = $cast->set($model, 'filters', [
        'filters' => [
            ['property' => 'id', 'value' => 1, 'operator' => '='],
        ],
        'operator' => 'and',
    ], []);

    expect($encoded)->toBeString();

    expect(fn () => $cast->set($model, 'filters', 'not-valid', []))
        ->toThrow(InvalidArgumentException::class);
});

it('throws for invalid node/group structures and handles null set', function (): void {
    $cast = new FiltersGroupCast();
    $model = new ACL();

    expect($cast->set($model, 'filters', null, []))->toBeNull();
    expect($cast->get($model, 'filters', '', []))->toBeNull();
    expect($cast->get($model, 'filters', ['property' => 'id', 'value' => 1, 'operator' => Modules\Core\Casts\FilterOperator::EQUALS], []))
        ->toBeInstanceOf(FiltersGroup::class);
    expect($cast->get($model, 'filters', ['filters' => [[['property' => 'id', 'value' => 1, 'operator' => '=']]]], []))
        ->toBeInstanceOf(FiltersGroup::class);
    expect(fn () => $cast->get($model, 'filters', ['foo' => 'bar'], []))
        ->toThrow(InvalidArgumentException::class);
});

it('skips invalid nested filter items and throws for invalid nested nodes', function (): void {
    $cast = new FiltersGroupCast();
    $model = new ACL();

    $group = $cast->get($model, 'filters', [
        'filters' => [
            'not-an-array',
            ['property' => 'name', 'value' => 'john', 'operator' => '='],
        ],
    ], []);

    expect($group)->toBeInstanceOf(FiltersGroup::class)
        ->and($group->filters)->toHaveCount(1);

    expect(fn () => $cast->get($model, 'filters', [
        'filters' => [
            ['invalid' => 'node'],
        ],
    ], []))->toThrow(InvalidArgumentException::class);
});
