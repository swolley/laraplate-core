<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Casts\CrudExecutor;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\RelationFilter;
use Modules\Core\Casts\ScopeFilter;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Field;
use Modules\Core\Models\Media;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Taxonomy;
use Modules\Core\Models\User;
use Modules\Core\Overrides\ContextualValidationException;
use Modules\Core\Rules\AclRelationFilters;
use Modules\Core\Rules\AclScopeFilters;
use Modules\Core\Support\PermissionName;
use Modules\Core\Tests\Stubs\ImpureScopeFilterStubModel;

beforeEach(function (): void {
    Cache::flush();
});

/**
 * An ACL on the `select` permission of a model, not yet saved, ready to be validated as a save would.
 *
 * @param  array<string, mixed>|FiltersGroup  $filters
 * @param  class-string<Model>  $model_class
 */
function aclscope_unsaved_acl(array|FiltersGroup $filters, string $model_class = User::class): ACL
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

/**
 * The validation messages a save of the ACL gives, empty when the ACL is accepted.
 */
function aclscope_save_errors(ACL $acl): string
{
    try {
        $acl->validateWithRules(CrudExecutor::INSERT);
    } catch (ContextualValidationException $exception) {
        return implode(' | ', array_merge(...array_values($exception->errors())));
    }

    return '';
}

/**
 * A morph relation filter of a media on its owner, holding the given nested node.
 *
 * @param  list<string>  $morph_types
 */
function aclscope_owner_filter(Filter|FiltersGroup|RelationFilter|ScopeFilter $node, array $morph_types): FiltersGroup
{
    return new FiltersGroup([new RelationFilter('model', new FiltersGroup([$node]), $morph_types)]);
}

it('accepts at save a declared scope of the entity, with literal parameters and with placeholders', function (ScopeFilter $scope): void {
    expect(aclscope_save_errors(aclscope_unsaved_acl(new FiltersGroup([$scope]))))->toBe('');
})->with([
    'no parameters' => [new ScopeFilter('valid')],
    'an ISO 8601 date' => [new ScopeFilter('validAt', ['2026-10-01T00:00:00Z'])],
    '@now in a date position' => [new ScopeFilter('validAt', ['@now'])],
    '@user in an int position' => [new ScopeFilter('expiring', ['@user.id'])],
]);

it('accepts at save a scope node written as JSON, deep inside groups', function (): void {
    $acl = aclscope_unsaved_acl(['operator' => 'and', 'filters' => [
        ['operator' => 'or', 'filters' => [['scope' => 'valid'], ['property' => 'name', 'operator' => '=', 'value' => 'x']]],
    ]]);

    expect(aclscope_save_errors($acl))->toBe('');
});

it('rejects at save a scope that is not usable on the entity, naming the scope and the reason but not the value', function (array|FiltersGroup $filters, string $scope, string $reason): void {
    $errors = aclscope_save_errors(aclscope_unsaved_acl($filters));

    expect($errors)->toContain(sprintf('"%s"', $scope))
        ->and($errors)->toContain($reason)
        ->and($errors)->not->toContain('secret-value-42');
})->with([
    'not declared on the entity' => [new FiltersGroup([new ScopeFilter('active')]), 'active', 'is not a filter scope'],
    'a local scope without the attribute' => [new FiltersGroup([new ScopeFilter('validityOrdered')]), 'validityOrdered', 'is not a filter scope'],
    'a bad parameter' => [new FiltersGroup([new ScopeFilter('validAt', ['secret-value-42'])]), 'validAt', 'ISO 8601'],
    'a missing parameter' => [new FiltersGroup([new ScopeFilter('validAt')]), 'validAt', 'needs at least 1'],
    'a date placeholder in an int position' => [new FiltersGroup([new ScopeFilter('expiring', ['@now'])]), 'expiring', 'not a date'],
    'deep inside groups, as JSON' => [['operator' => 'and', 'filters' => [['operator' => 'or', 'filters' => [['scope' => 'active'], ['property' => 'id', 'operator' => '=', 'value' => 1]]]]], 'active', 'is not a filter scope'],
]);

it('rejects at save an impure scope', function (): void {
    $errors = aclscope_save_errors(aclscope_unsaved_acl(aclscope_owner_filter(new ScopeFilter('joined'), [ImpureScopeFilterStubModel::class]), Media::class));

    expect($errors)->toContain('"joined"')
        ->and($errors)->toContain('does more than add where conditions');
});

it('checks a scope inside a relation filter against the related model', function (): void {
    $license_valid = aclscope_unsaved_acl(new FiltersGroup([new RelationFilter('license', new FiltersGroup([new ScopeFilter('valid')]))]));
    $license_active = aclscope_unsaved_acl(new FiltersGroup([new RelationFilter('license', new FiltersGroup([new ScopeFilter('active')]))]));

    expect(aclscope_save_errors($license_valid))->toBe('')
        ->and(aclscope_save_errors($license_active))->toContain('"active" is not a filter scope of Modules\Core\Models\License');
});

it('checks a scope inside a morph relation filter against each morph type', function (): void {
    $active = new ScopeFilter('active');

    expect(aclscope_save_errors(aclscope_unsaved_acl(aclscope_owner_filter($active, [Field::class]), Media::class)))->toBe('')
        ->and(aclscope_save_errors(aclscope_unsaved_acl(aclscope_owner_filter($active, [Field::class, User::class]), Media::class)))
        ->toContain('"active" is not a filter scope of ' . User::class)
        ->and(aclscope_save_errors(aclscope_unsaved_acl(aclscope_owner_filter(new ScopeFilter('valid'), [Field::class, User::class]), Media::class)))
        ->toContain('"valid" is not a filter scope of ' . Field::class);
});

it('rejects at save a scope node when the entity of the permission cannot be resolved', function (): void {
    $permission = Permission::query()->create(['name' => 'default.nowhere_table.select', 'guard_name' => 'api']);
    $acl = new ACL;
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => new FiltersGroup([new ScopeFilter('valid')], WhereClause::And),
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);

    expect(aclscope_save_errors($acl))->toContain('holds a scope filter but the entity of the permission cannot be resolved');
});

it('leaves an ACL without scope nodes to the other rules', function (): void {
    $acl = aclscope_unsaved_acl(new FiltersGroup([new Filter('name', 'x', FilterOperator::Equals)]));

    expect(aclscope_save_errors($acl))->toBe('');
});

it('rejects at save an abstract model given as a morph type with a validation error, not a crash', function (Filter|ScopeFilter $node): void {
    $errors = aclscope_save_errors(aclscope_unsaved_acl(aclscope_owner_filter($node, [Taxonomy::class]), Media::class));

    expect($errors)->toContain(sprintf('morph type "%s" is not a model', Taxonomy::class));
})->with([
    'a column filter' => [new Filter('name', 'x', FilterOperator::Equals)],
    'a scope node' => [new ScopeFilter('active')],
]);

it('refuses a filter node of an unknown kind in both target rules instead of skipping it', function (string $rule_class): void {
    $permission = Permission::query()->firstOrCreate(['name' => PermissionName::forClass(User::class, 'select'), 'guard_name' => 'api']);
    $failures = [];

    (new $rule_class)->setData(['permission_id' => $permission->id])->validate(
        'filters',
        new FiltersGroup([new FiltersGroup([new stdClass()])]),
        function (string $message) use (&$failures): void {
            $failures[] = $message;
        },
    );

    expect($failures)->toBe(['filters holds a filter node of an unknown kind']);
})->with([AclScopeFilters::class, AclRelationFilters::class]);

it('refuses a scope filter when the permission of the ACL is not chosen or does not resolve to an entity', function (array $data): void {
    $failures = [];
    $rule = new AclScopeFilters()->setData($data);

    $rule->validate('filters', new FiltersGroup([new ScopeFilter('valid')]), function (string $message) use (&$failures): void {
        $failures[] = $message;
    });

    expect($failures)->toBe(['filters holds a scope filter but the entity of the permission cannot be resolved']);
})->with([
    'no permission_id' => [[]],
    'null permission_id' => [['permission_id' => null]],
    'unknown permission_id' => [['permission_id' => 999_999]],
]);

it('accepts filters without a scope node when the permission of the ACL is not chosen', function (): void {
    $failures = [];
    $rule = new AclScopeFilters()->setData(['permission_id' => null]);

    $rule->validate('filters', new FiltersGroup([new Filter('name', 'x', FilterOperator::Equals)]), function (string $message) use (&$failures): void {
        $failures[] = $message;
    });

    expect($failures)->toBe([]);
});
