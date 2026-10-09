<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\SearchMode;
use Modules\Core\Casts\SearchRequestData;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\DTOs\AdvancedSearchResult;
use Modules\Core\Search\Services\AdvancedSearchService;
use Modules\Core\Search\Services\EnsembleSearchService;
use Modules\Core\Search\Services\FallbackSearchPlanner;
use Modules\Core\Search\Services\SimpleQueryIntentParser;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\CrudService;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\Core\Tests\Stubs\Search\AclRehydrationUserStub;
use Modules\Core\Tests\Stubs\Search\FixedSearchStrategyResolver;

/**
 * The ACL row filters of a user reach the search engine as filters. They are not the only barrier: the
 * records behind the hits are reloaded by key, and that reload applies the same filters to the database,
 * so a hit the engine should not have returned (a strategy that dropped the filter, an index out of date)
 * never reaches the user.
 */
function crud_acl_set(object $object, string $property, mixed $value): void
{
    (new ReflectionProperty($object, $property))->setValue($object, $value);
}

/**
 * @param  list<int|string>  $ids
 */
function crud_acl_search_returning(array $ids): AdvancedSearchService
{
    $ensemble = Mockery::mock(EnsembleSearchService::class);
    $ensemble->shouldReceive('search')->andReturnUsing(static fn (Model $model, string $query, array $plan, ?array $vector, int $page, int $perPage): AdvancedSearchResult => new AdvancedSearchResult(
        hits: array_map(static fn (int|string $id): array => ['id' => (string) $id, 'score' => 1.0, 'source' => []], $ids),
        total: count($ids),
        page: $page,
        perPage: $perPage,
        totalPages: 1,
        meta: [],
    ));

    return new AdvancedSearchService(
        new FixedSearchStrategyResolver(planner: new FallbackSearchPlanner, intent_parser: new SimpleQueryIntentParser),
        $ensemble, app());
}

function crud_acl_search_data(Model $model, User $user): SearchRequestData
{
    $request = new class(['qs' => 'needle']) extends Request
    {
        public function validated(?string $key = null, mixed $default = null): mixed
        {
            return $key === null ? $this->all() : ($this->all()[$key] ?? $default);
        }
    };
    $request->setUserResolver(static fn () => $user);

    $data = (new ReflectionClass(SearchRequestData::class))->newInstanceWithoutConstructor();

    foreach ([
        'request' => $request,
        'mainEntity' => $model->getTable(),
        'primaryKey' => $model->getKeyName(),
        'model' => $model,
        'connection' => $model->getConnectionName(),
        'columns' => [],
        'relations' => [],
        'sort' => [],
        'filters' => null,
        'group_by' => [],
        'qs' => 'needle',
        'mode' => SearchMode::Orchestrated,
        'page' => null,
        'limit' => 5,
        'pagination' => 5,
        'from' => null,
        'to' => null,
        'count' => false,
    ] as $property => $value) {
        crud_acl_set($data, $property, $value);
    }

    return $data;
}

/**
 * A user who may select users, but only the rows the ACL names.
 *
 * @param  list<int|string>  $allowed_ids
 */
function crud_acl_restricted_user(array $allowed_ids): User
{
    $permission = Permission::create(['name' => 'default.users.select', 'guard_name' => 'web']);
    $role = Role::factory()->create(['name' => 'acl_role_' . uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => new FiltersGroup([new Filter('id', $allowed_ids, FilterOperator::In)], WhereClause::And),
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $user = User::factory()->create();
    $user->assignRole($role);
    Auth::login($user);

    return $user;
}

function crud_acl_run(array $hit_ids, User $user): array
{
    config()->set('scout.driver', 'elasticsearch');

    $engine = Mockery::mock(ISearchEngine::class);
    $engine->shouldReceive('supportsOrchestratedSearch')->andReturnTrue();
    AclRehydrationUserStub::$engine = $engine;

    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class), crud_acl_search_returning($hit_ids));
    $result = $service->search(crud_acl_search_data(new AclRehydrationUserStub, $user));

    return $result->data->map(static fn (Model $record): mixed => $record->getKey())->all();
}

it('drops a hit the ACL row filters do not allow, even when the engine returned it', function (): void {
    $allowed = User::factory()->create();
    $denied = User::factory()->create();
    $user = crud_acl_restricted_user([$allowed->getKey()]);

    // The engine, as if it had lost the filter on the way, returns both rows.
    expect(crud_acl_run([$allowed->getKey(), $denied->getKey()], $user))->toBe([$allowed->getKey()]);
});

it('keeps the order of the hits that are allowed', function (): void {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $denied = User::factory()->create();
    $user = crud_acl_restricted_user([$first->getKey(), $second->getKey()]);

    expect(crud_acl_run([$second->getKey(), $denied->getKey(), $first->getKey()], $user))->toBe([$second->getKey(), $first->getKey()]);
});

it('returns every hit to a user the ACL does not restrict', function (): void {
    $one = User::factory()->create();
    $two = User::factory()->create();

    $role = Role::factory()->create(['name' => config('permission.roles.superadmin'), 'guard_name' => 'web']);
    $admin = User::factory()->create();
    $admin->assignRole($role);
    Auth::login($admin);

    expect(crud_acl_run([$one->getKey(), $two->getKey()], $admin))->toBe([$one->getKey(), $two->getKey()]);
});
