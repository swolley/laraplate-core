<?php

declare(strict_types=1);

use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\ScopeFilter;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\Media;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Search\Services\ScoutSearchConstraintApplier;
use Modules\Core\Support\CrudApiExposure;
use Modules\Core\Tests\Stubs\Search\RecordingEngineBuilderStub;

/*
 * A search engine takes no scope node (S8): it is left out of the engine filters exactly as a relation filter
 * is, and the records behind the hits are checked against it when they are reloaded from the database.
 */

it('drops a scope node from an AND group and keeps the column condition beside it', function (): void {
    $builder = new RecordingEngineBuilderStub;
    $filters = new FiltersGroup([
        new Filter('collection_name', 'covers', FilterOperator::Equals),
        new ScopeFilter('valid'),
        new FiltersGroup([new ScopeFilter('validAt', ['2026-08-19T00:00:00Z'])]),
    ]);

    app(ScoutSearchConstraintApplier::class)->apply($builder, new Media, $filters);

    expect($builder->calls)->toBe([['method' => 'where', 'field' => 'collection_name', 'value' => 'covers']])
        ->and($builder->options)->toBe([]);
});

it('pushes nothing for an OR group that holds a scope node, at any depth', function (FiltersGroup $or_group): void {
    $builder = new RecordingEngineBuilderStub;
    $filters = new FiltersGroup([
        new Filter('collection_name', 'covers', FilterOperator::Equals),
        $or_group,
    ]);

    app(ScoutSearchConstraintApplier::class)->apply($builder, new Media, $filters);

    expect($builder->calls)->toBe([['method' => 'where', 'field' => 'collection_name', 'value' => 'covers']])
        ->and($builder->options)->toBe([]);
})->with([
    'beside a column filter' => fn (): FiltersGroup => new FiltersGroup([
        new Filter('collection_name', 'avatars', FilterOperator::Equals),
        new ScopeFilter('valid'),
    ], WhereClause::Or),
    'alone in an AND group inside it' => fn (): FiltersGroup => new FiltersGroup([
        new Filter('collection_name', 'avatars', FilterOperator::Equals),
        new FiltersGroup([new ScopeFilter('valid')]),
    ], WhereClause::Or),
]);

it('pushes nothing when the root OR group holds a scope node', function (): void {
    $builder = new RecordingEngineBuilderStub;
    $filters = new FiltersGroup([
        new Filter('collection_name', 'covers', FilterOperator::Equals),
        new ScopeFilter('valid'),
    ], WhereClause::Or);

    app(ScoutSearchConstraintApplier::class)->apply($builder, new Media, $filters);

    expect($builder->calls)->toBe([])
        ->and($builder->options)->toBe([]);
});

it('refuses a node kind it does not know instead of skipping it', function (FiltersGroup $filters): void {
    expect(fn () => app(ScoutSearchConstraintApplier::class)->apply(new RecordingEngineBuilderStub, new Media, $filters))
        ->toThrow(InvalidArgumentException::class, 'stdClass');
})->with([
    'in an AND group' => fn (): FiltersGroup => new FiltersGroup([new stdClass]),
    'in an OR group' => fn (): FiltersGroup => new FiltersGroup([
        new Filter('collection_name', 'covers', FilterOperator::Equals),
        new stdClass,
    ], WhereClause::Or),
]);

it('refuses with 422 a scope the model exposes only to ACLs in a search request', function (): void {
    CrudApiExposure::enable();
    config()->set('permission.roles.superadmin', 'superadmin');
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin', 'web'));

    $response = $this->actingAs($user)->getJson('/api/v1/search/core/cron_jobs?' . http_build_query([
        'qs' => 'job',
        'filters' => json_encode(['scope' => 'active'], JSON_THROW_ON_ERROR),
    ]));

    $response->assertUnprocessable()->assertJsonValidationErrors('filters');
});

it('gives the reload the database-only nodes of an AND group, and nothing when the engine takes the whole tree', function (): void {
    $scope = new ScopeFilter('valid');
    $nested_scope = new ScopeFilter('expired');
    $filters = new FiltersGroup([
        new Filter('collection_name', 'covers', FilterOperator::Equals),
        $scope,
        new FiltersGroup([new Filter('name', 'cover', FilterOperator::Equals), $nested_scope]),
    ]);

    expect(ScoutSearchConstraintApplier::databaseOnlyPart($filters))
        ->toEqual(new FiltersGroup([$scope, new FiltersGroup([$nested_scope])]))
        ->and(ScoutSearchConstraintApplier::databaseOnlyPart(new FiltersGroup([new Filter('collection_name', 'covers', FilterOperator::Equals)])))
        ->toBeNull();
});

it('gives the reload a whole OR group the engine received wider than it is', function (): void {
    // The engine receives `collection_name = covers OR name = cover`: the scope beside `covers` is dropped from
    // its AND group, which widens the OR group, so only the whole group restores it.
    $or_group = new FiltersGroup([
        new FiltersGroup([new Filter('collection_name', 'covers', FilterOperator::Equals), new ScopeFilter('valid')]),
        new Filter('name', 'cover', FilterOperator::Equals),
    ], WhereClause::Or);
    $builder = new RecordingEngineBuilderStub;

    app(ScoutSearchConstraintApplier::class)->apply($builder, new Media, new FiltersGroup([$or_group]));

    expect($builder->options['advanced_filters'] ?? null)->toBe(['operator' => 'and', 'filters' => [[
        'operator' => 'or',
        'filters' => [
            ['operator' => 'and', 'filters' => [['field' => 'collection_name', 'operator' => '=', 'value' => 'covers']]],
            ['field' => 'name', 'operator' => '=', 'value' => 'cover'],
        ],
    ]]])
        ->and(ScoutSearchConstraintApplier::databaseOnlyPart(new FiltersGroup([$or_group])))->toEqual(new FiltersGroup([$or_group]))
        ->and(ScoutSearchConstraintApplier::databaseOnlyPart($or_group))->toBe($or_group);
});
