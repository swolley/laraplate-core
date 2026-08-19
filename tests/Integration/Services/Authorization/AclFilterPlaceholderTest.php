<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;

/**
 * @return mixed the private method's return value
 */
function acl_invoke(AuthorizationService $service, string $method, mixed ...$args): mixed
{
    $ref = new ReflectionMethod(AuthorizationService::class, $method);
    $ref->setAccessible(true);

    return $ref->invoke($service, ...$args);
}

function acl_service(): AuthorizationService
{
    return app(AuthorizationService::class);
}

afterEach(function (): void {
    Carbon::setTestNow();
});

it('substitutes the @now placeholder with the current time', function (): void {
    Carbon::setTestNow('2026-08-19 12:00:00');

    $resolved = acl_invoke(acl_service(), 'resolveFilterValue', '@now');

    expect($resolved)->toBeInstanceOf(Carbon::class)
        ->and($resolved->toDateTimeString())->toBe('2026-08-19 12:00:00');
});

it('substitutes the @today placeholder with the start of the day', function (): void {
    Carbon::setTestNow('2026-08-19 12:34:56');

    $resolved = acl_invoke(acl_service(), 'resolveFilterValue', '@today');

    expect($resolved->toDateTimeString())->toBe('2026-08-19 00:00:00');
});

it('leaves non-placeholder values untouched and resolves arrays element-wise', function (): void {
    Carbon::setTestNow('2026-08-19 12:00:00');
    $service = acl_service();

    expect(acl_invoke($service, 'resolveFilterValue', 'published'))->toBe('published')
        ->and(acl_invoke($service, 'resolveFilterValue', 42))->toBe(42);

    $array = acl_invoke($service, 'resolveFilterValue', ['@now', 'literal']);
    expect($array[0])->toBeInstanceOf(Carbon::class)
        ->and($array[0]->toDateTimeString())->toBe('2026-08-19 12:00:00')
        ->and($array[1])->toBe('literal');
});

it('binds the resolved @now value when applying a filter group to a query', function (): void {
    Carbon::setTestNow('2026-08-19 12:00:00');

    $group = new FiltersGroup(
        filters: [new Filter('valid_from', '@now', FilterOperator::LessEquals)],
        operator: WhereClause::And,
    );

    $query = User::query();
    acl_invoke(acl_service(), 'applyFiltersRecursively', $query, $group);

    $bindings = $query->getQuery()->getRawBindings()['where'];
    expect($bindings)->toHaveCount(1)
        ->and((string) $bindings[0])->toBe('2026-08-19 12:00:00');
});
