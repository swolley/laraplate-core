<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Casts\CrudExecutor;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\FiltersGroupCast;
use Modules\Core\Casts\RelationFilter;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Media;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Overrides\ContextualValidationException;
use Modules\Core\Rules\QueryBuilder as QueryBuilderRule;
use Modules\Core\Search\Services\ScoutSearchConstraintApplier;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\CrudService;
use Modules\Core\Support\CrudApiExposure;
use Modules\Core\Support\PermissionName;
use Modules\Core\Tests\Stubs\Search\RecordingEngineBuilderStub;

beforeEach(function (): void {
    Cache::flush();
    Carbon::setTestNow('2026-08-19 12:00:00');
    config()->set('permission.roles.superadmin', 'superadmin');
    config()->set('core.search.vector.enabled', false);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * A media row owned by the given model, as a Spatie media owned by a `HasMedia` model.
 */
function aclrel_media(string $owner_type, int|string $owner_id): Media
{
    $media = new Media;
    $media->forceFill([
        'collection_name' => 'default',
        'name' => 'photo',
        'file_name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'disk' => 'public',
        'size' => 123,
        'model_type' => $owner_type,
        'model_id' => $owner_id,
        'custom_properties' => [],
        'manipulations' => [],
        'generated_conversions' => [],
        'responsive_images' => [],
    ]);
    $media->saveQuietly();

    return $media;
}

/**
 * The owner condition of a media: its owner is a user created at or before the moment of the request.
 */
function aclrel_owner_filters(string $name): FiltersGroup
{
    return new FiltersGroup(
        filters: [
            new RelationFilter(
                relation: 'model',
                filters: new FiltersGroup(
                    filters: [
                        new Filter('name', $name, FilterOperator::Equals),
                        new Filter('created_at', '@now', FilterOperator::LessEquals),
                    ],
                    operator: WhereClause::And,
                ),
                morph_types: [User::class],
            ),
        ],
        operator: WhereClause::And,
    );
}

/**
 * A role on the `api` guard holding `vend_media.select`, narrowed by an ACL.
 *
 * @return array{0: App\Models\User, 1: string}
 */
function aclrel_media_reader(FiltersGroup $filters): array
{
    $permission_name = PermissionName::forClass(Media::class, 'select');
    Permission::query()->firstOrCreate(['name' => $permission_name, 'guard_name' => 'web']);
    $permission = Permission::query()->firstOrCreate(['name' => $permission_name, 'guard_name' => 'api']);

    $role = Role::factory()->create(['name' => 'aclrel_reader_' . uniqid(), 'guard_name' => 'api']);
    $role->givePermissionTo($permission);

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'role_id' => $role->id,
        'filters' => $filters,
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $user = App\Models\User::query()->findOrFail(User::factory()->create()->getKey());
    $user->assignRole($role);

    return [$user->fresh(), $permission_name];
}

it('round-trips a relation filter through the filters column of an ACL', function (): void {
    $permission = Permission::query()->create(['name' => 'default.vend_media.select', 'guard_name' => 'api']);
    $filters = aclrel_owner_filters('owner');

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => $filters,
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $stored = $acl->fresh()->filters;
    $relation = $stored->filters[0];

    expect($relation)->toBeInstanceOf(RelationFilter::class)
        ->and($relation->relation)->toBe('model')
        ->and($relation->morph_types)->toBe([User::class])
        ->and($relation->filters->filters)->toHaveCount(2)
        ->and($relation->filters->filters[1]->value)->toBe('@now')
        ->and($stored->toArray())->toBe($filters->toArray());

    $raw = json_decode((string) $acl->fresh()->getRawOriginal('filters'), true);

    expect($raw['filters'][0])->toHaveKeys(['relation', 'morph_types', 'filters'])
        ->and($raw['filters'][0]['relation'])->toBe('model');
});

it('hydrates a relation filter from its JSON and leaves morph_types unset for a plain relation', function (): void {
    $cast = new FiltersGroupCast;
    $hydrated = $cast->get(new ACL, 'filters', json_encode([
        'operator' => 'and',
        'filters' => [
            ['relation' => 'content', 'filters' => ['operator' => 'and', 'filters' => [['property' => 'id', 'operator' => '=', 'value' => 1]]]],
        ],
    ]), []);

    $relation = $hydrated->filters[0];

    expect($relation)->toBeInstanceOf(RelationFilter::class)
        ->and($relation->morph_types)->toBeNull()
        ->and($relation->filters->filters[0])->toBeInstanceOf(Filter::class)
        ->and($relation->toArray())->not->toHaveKey('morph_types');
});

it('accepts a relation filter in the ACL validation rule and refuses a malformed one', function (): void {
    $failures = [];
    $fail = static function (string $message) use (&$failures): void {
        $failures[] = $message;
    };

    (new QueryBuilderRule)->validate('filters', aclrel_owner_filters('owner')->toArray(), $fail);
    expect($failures)->toBe([]);

    (new QueryBuilderRule)->validate('filters', ['filters' => [['relation' => 'model']], 'operator' => 'and'], $fail);
    expect($failures)->not->toBe([]);
});

/**
 * An ACL on the `select` permission of a model (Media by default), not yet saved, ready to be validated as a save would.
 *
 * @param  array<string, mixed>|FiltersGroup  $filters
 * @param  class-string<Illuminate\Database\Eloquent\Model>  $model_class
 */
function aclrel_unsaved_media_acl(array|FiltersGroup $filters, string $model_class = Media::class): ACL
{
    $permission = Permission::query()->firstOrCreate([
        'name' => PermissionName::forClass($model_class, 'select'),
        'guard_name' => 'api',
    ]);

    $acl = new ACL;
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => $filters,
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);

    return $acl;
}

it('accepts at save a relation filter that names a real relation and a real morph type', function (): void {
    $acl = aclrel_unsaved_media_acl(aclrel_owner_filters('owner'));

    expect(fn () => $acl->validateWithRules(CrudExecutor::INSERT))->not->toThrow(ContextualValidationException::class);
});

it('rejects at save a relation filter whose relation does not exist on the entity', function (): void {
    $acl = aclrel_unsaved_media_acl(new FiltersGroup([
        new RelationFilter('nowhere', new FiltersGroup([new Filter('id', 1, FilterOperator::Equals)])),
    ]));

    expect(fn () => $acl->validateWithRules(CrudExecutor::INSERT))->toThrow(ContextualValidationException::class);
});

it('rejects at save a relation filter that names a method which is not a relation', function (): void {
    $acl = aclrel_unsaved_media_acl(new FiltersGroup([
        new RelationFilter('delete', new FiltersGroup([new Filter('id', 1, FilterOperator::Equals)])),
    ]));

    expect(fn () => $acl->validateWithRules(CrudExecutor::INSERT))->toThrow(ContextualValidationException::class);
});

it('rejects at save a morph relation without morph types and a plain relation with morph types', function (): void {
    $nested = new FiltersGroup([new Filter('id', 1, FilterOperator::Equals)]);

    $morph_without_types = aclrel_unsaved_media_acl(new FiltersGroup([new RelationFilter('model', $nested)]));
    $plain_with_types = aclrel_unsaved_media_acl(new FiltersGroup([
        new RelationFilter('roles', $nested, [Role::class]),
    ]), User::class);

    expect(fn () => $morph_without_types->validateWithRules(CrudExecutor::INSERT))->toThrow(ContextualValidationException::class);
    expect(fn () => $plain_with_types->validateWithRules(CrudExecutor::INSERT))->toThrow(ContextualValidationException::class);
});

it('rejects at save a morph type that is not a model class, from the JSON array form too', function (): void {
    $nested = ['filters' => [['property' => 'id', 'operator' => '=', 'value' => 1]], 'operator' => 'and'];

    $unknown_class = aclrel_unsaved_media_acl(new FiltersGroup([
        new RelationFilter('model', new FiltersGroup([new Filter('id', 1, FilterOperator::Equals)]), ['Modules\\Nowhere\\Models\\Ghost']),
    ]));
    $not_a_model = aclrel_unsaved_media_acl(['operator' => 'and', 'filters' => [
        ['relation' => 'model', 'morph_types' => [stdClass::class], 'filters' => $nested],
    ]]);

    expect(fn () => $unknown_class->validateWithRules(CrudExecutor::INSERT))->toThrow(ContextualValidationException::class);
    expect(fn () => $not_a_model->validateWithRules(CrudExecutor::INSERT))->toThrow(ContextualValidationException::class);
});

it('rejects at save a nested relation filter that is wrong for the related entity', function (): void {
    $acl = aclrel_unsaved_media_acl(new FiltersGroup([
        new RelationFilter(
            'model',
            new FiltersGroup([new RelationFilter('nowhere', new FiltersGroup([new Filter('id', 1, FilterOperator::Equals)]))]),
            [User::class],
        ),
    ]));

    expect(fn () => $acl->validateWithRules(CrudExecutor::INSERT))->toThrow(ContextualValidationException::class);
});

it('refuses a relation filter that names no morph type when it is given an empty list', function (): void {
    expect(fn () => new RelationFilter('model', new FiltersGroup, []))->toThrow(InvalidArgumentException::class);
});

it('keeps on a list only the media whose morph owner matches the nested filters, resolving @now', function (): void {
    CrudApiExposure::enable();
    $kept_owner = User::factory()->create(['name' => 'kept-owner']);
    $other_owner = User::factory()->create(['name' => 'other-owner']);
    $future_owner = User::factory()->create(['name' => 'kept-owner']);
    $future_owner->forceFill(['created_at' => now()->addWeek()])->saveQuietly();

    $kept = aclrel_media($kept_owner->getMorphClass(), $kept_owner->getKey());
    aclrel_media($other_owner->getMorphClass(), $other_owner->getKey());
    aclrel_media($future_owner->getMorphClass(), $future_owner->getKey());
    aclrel_media('Modules\\Nowhere\\Models\\Ghost', 1);

    [$reader] = aclrel_media_reader(aclrel_owner_filters('kept-owner'));

    $response = $this->actingAs($reader)->getJson('/api/v1/select/core/media');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$kept->id]);
});

it('applies the relation filter on a detail too: the record behind a matching owner is returned, any other is not found', function (): void {
    CrudApiExposure::enable();
    $kept_owner = User::factory()->create(['name' => 'kept-owner']);
    $other_owner = User::factory()->create(['name' => 'other-owner']);
    $kept = aclrel_media($kept_owner->getMorphClass(), $kept_owner->getKey());
    $hidden = aclrel_media($other_owner->getMorphClass(), $other_owner->getKey());

    [$reader] = aclrel_media_reader(aclrel_owner_filters('kept-owner'));

    $this->actingAs($reader)->getJson('/api/v1/detail/core/media?id=' . $kept->id)
        ->assertOk()
        ->assertJsonPath('data.id', $kept->id);
    $this->actingAs($reader)->getJson('/api/v1/detail/core/media?id=' . $hidden->id)
        ->assertNotFound();
});

it('applies the same relation filter when the ACL is applied straight to a query', function (): void {
    $kept_owner = User::factory()->create(['name' => 'kept-owner']);
    $other_owner = User::factory()->create(['name' => 'other-owner']);
    $kept = aclrel_media($kept_owner->getMorphClass(), $kept_owner->getKey());
    aclrel_media($other_owner->getMorphClass(), $other_owner->getKey());

    [$reader, $permission_name] = aclrel_media_reader(aclrel_owner_filters('kept-owner'));
    Auth::shouldUse('api');
    Auth::guard('api')->setUser($reader);

    $query = Media::query();
    app(AuthorizationService::class)->applyAclFiltersToQuery($query, $permission_name);

    expect($query->pluck('id')->all())->toBe([$kept->id]);
});

it('combines a relation filter with a column filter in an OR group', function (): void {
    $owner = User::factory()->create(['name' => 'kept-owner']);
    $other = User::factory()->create(['name' => 'other-owner']);
    $by_owner = aclrel_media($owner->getMorphClass(), $owner->getKey());
    $by_column = aclrel_media($other->getMorphClass(), $other->getKey());
    $by_column->forceFill(['collection_name' => 'covers'])->saveQuietly();
    $neither = aclrel_media($other->getMorphClass(), $other->getKey());

    $filters = new FiltersGroup(
        filters: [
            aclrel_owner_filters('kept-owner'),
            new FiltersGroup([new Filter('collection_name', 'covers', FilterOperator::Equals)]),
        ],
        operator: WhereClause::Or,
    );
    [$reader, $permission_name] = aclrel_media_reader($filters);
    Auth::shouldUse('api');
    Auth::guard('api')->setUser($reader);

    $query = Media::query()->orderBy('id');
    app(AuthorizationService::class)->applyAclFiltersToQuery($query, $permission_name);

    expect($query->pluck('id')->all())->toBe([$by_owner->id, $by_column->id])
        ->and($query->pluck('id')->all())->not->toContain($neither->id);
});

it('does not push a relation filter to the search engine and keeps the column conditions beside it', function (): void {
    $builder = new RecordingEngineBuilderStub;
    $filters = new FiltersGroup(
        filters: [
            new Filter('collection_name', 'covers', FilterOperator::Equals),
            aclrel_owner_filters('kept-owner'),
        ],
        operator: WhereClause::And,
    );

    app(ScoutSearchConstraintApplier::class)->apply($builder, new Media, $filters);

    expect($builder->calls)->toBe([['method' => 'where', 'field' => 'collection_name', 'value' => 'covers']])
        ->and($builder->options)->toBe([]);
});

it('pushes nothing for an OR group that holds a relation filter, so the engine never drops what the relation allows', function (): void {
    $builder = new RecordingEngineBuilderStub;
    $filters = new FiltersGroup(
        filters: [
            new Filter('collection_name', 'covers', FilterOperator::Equals),
            aclrel_owner_filters('kept-owner'),
        ],
        operator: WhereClause::Or,
    );

    app(ScoutSearchConstraintApplier::class)->apply($builder, new Media, $filters);

    expect($builder->calls)->toBe([])
        ->and($builder->options)->toBe([]);
});

it('drops, when the hits are reloaded, the hits whose related record does not match', function (): void {
    $kept_owner = User::factory()->create(['name' => 'kept-owner']);
    $other_owner = User::factory()->create(['name' => 'other-owner']);
    $kept = aclrel_media($kept_owner->getMorphClass(), $kept_owner->getKey());
    $dropped = aclrel_media($other_owner->getMorphClass(), $other_owner->getKey());
    config()->set('core.media.search_visibility', 'open');

    [$reader, $permission_name] = aclrel_media_reader(aclrel_owner_filters('kept-owner'));
    Auth::shouldUse('api');
    Auth::guard('api')->setUser($reader);

    $hits_query = new ReflectionMethod(CrudService::class, 'searchHitsQuery');
    $query = $hits_query->invoke(app(CrudService::class), new Media, [$dropped->id, $kept->id], $permission_name);

    expect($query->pluck('id')->all())->toBe([$kept->id]);
});
