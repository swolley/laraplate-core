<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\Column;
use Modules\Core\Casts\ColumnType;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\Sort;
use Modules\Core\Casts\SortDirection;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

/**
 * @param  array<int,Column>  $columns
 * @param  array<int,string|array{name:string}>  $relations
 * @param  array<int,Sort>  $sort
 */
function qb_make_list_request_data(array $columns, array $relations = [], array $sort = [], ?FiltersGroup $filters = null): Modules\Core\Casts\ListRequestData
{
    $ref = new ReflectionClass(Modules\Core\Casts\ListRequestData::class);

    /** @var Modules\Core\Casts\ListRequestData $data */
    $data = $ref->newInstanceWithoutConstructor();

    $set = function (object $obj, string $prop, mixed $value): void {
        $p = new ReflectionProperty($obj, $prop);
        $p->setAccessible(true);
        $p->setValue($obj, $value);
    };

    $set($data, 'columns', $columns);
    $set($data, 'relations', $relations);
    $set($data, 'sort', $sort);
    $set($data, 'filters', $filters);

    return $data;
}

it('prepareQuery auto-injects relations when relation columns are requested', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.name', ColumnType::COLUMN),
        ],
        relations: [],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    expect($query->getEagerLoads())->toHaveKey('roles');
});

it('prepareQuery selects requested columns and always includes primary key', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data([
        new Column('users.username', ColumnType::COLUMN),
    ]);

    (new QueryBuilder())->prepareQuery($query, $request_data);

    /** @var array<int,string>|null $selected */
    $selected = $query->getQuery()->columns;

    expect($selected)->not->toBeNull();
    expect($selected)->toContain('id');
    expect($selected)->toContain('username');
});

it('prepareQuery adds foreign keys to selected columns (unless selecting all)', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data([
        new Column('users.username', ColumnType::COLUMN),
    ]);

    (new QueryBuilder())->prepareQuery($query, $request_data);

    /** @var array<int,string>|null $selected */
    $selected = $query->getQuery()->columns;

    expect($selected)->not->toBeNull();

    // `users.license_id` is a FK in this module and should be auto-selected to keep relations working.
    expect($selected)->toContain('license_id');
});

it('prepareQuery forces select all when computed columns are requested but dependencies are unknown', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data([
        new Column('users.username', ColumnType::COLUMN),
        new Column('users.someComputed', ColumnType::APPEND),
    ]);

    (new QueryBuilder())->prepareQuery($query, $request_data);

    /** @var array<int,string>|null $selected */
    $selected = $query->getQuery()->columns;

    expect($selected)->not->toBeNull();
    expect($selected)->toContain('users.*');
});

it('prepareQuery registers eager load closures for relations and applies relation sort', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.name', ColumnType::COLUMN),
        ],
        relations: ['roles'],
        sort: [
            new Sort('roles.name', SortDirection::ASC),
        ],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $withs = $query->getEagerLoads();

    expect($withs)->toHaveKey('roles');

    $user = User::factory()->create();
    $relation = $user->roles();

    $withs['roles']($relation);

    $orders = $relation->getQuery()->getQuery()->orders ?? [];

    expect($orders)->not->toBeEmpty();
    expect($orders[0]['column'])->toBe('name');
    expect(mb_strtolower((string) $orders[0]['direction']))->toBe('asc');
});

it('prepareQuery applies aggregates on relations (withCount)', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles', ColumnType::COUNT),
        ],
        relations: ['roles'],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $sql = $query->toSql();

    expect(mb_strtolower($sql))->toContain('roles_count');
});

it('relation deleted_at filter requires delete permission to include trashed relations', function (): void {
    $role = Role::factory()->create(['name' => 'to_be_deleted', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    $role->delete();

    $filters = new FiltersGroup([
        new Filter('roles.deleted_at', null, FilterOperator::NOT_EQUALS),
    ], WhereClause::AND);

    $query_without_permission = User::query();
    (new QueryBuilder())->applyFilters($query_without_permission, $filters);

    expect($query_without_permission->pluck('id')->all())->toBe([]);

    $qb_ref = new ReflectionClass(QueryBuilder::class);
    $split_property = $qb_ref->getMethod('splitProperty');
    $split_property->setAccessible(true);
    $splitted = $split_property->invoke(new QueryBuilder(), new User(), 'roles.deleted_at');

    $permission_name = sprintf('%s.%s.delete', $splitted['connection'], $splitted['table']);
    $permission = Permission::factory()->create(['name' => $permission_name, 'guard_name' => 'web']);
    $permission_holder_role = Role::factory()->create(['name' => 'can_view_trashed_roles', 'guard_name' => 'web']);
    $permission_holder_role->givePermissionTo($permission);

    $privileged_user = User::factory()->create();
    $privileged_user->assignRole($permission_holder_role);

    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $permission_holder_role->refresh();
    $privileged_user->refresh();

    expect(Permission::query()->where('name', $permission_name)->exists())->toBeTrue();
    expect($permission_holder_role->hasPermissionTo($permission_name))->toBeTrue();
    expect($privileged_user->hasPermissionTo($permission_name))->toBeTrue();

    Gate::define($permission_name, fn (User $user): bool => $user->hasPermissionTo($permission_name));
    expect($privileged_user->can($permission_name))->toBeTrue();
    auth()->login($privileged_user);

    $manual_ids = User::query()
        ->whereHas('roles', fn (Builder $q) => $q->withTrashed()->whereNotNull('deleted_at'))
        ->pluck('id')
        ->all();
    expect($manual_ids)->toContain($user->id);

    $query_with_permission = User::query();
    (new QueryBuilder())->applyFilters($query_with_permission, $filters);

    $manual_sql = mb_strtolower(User::query()->whereHas('roles', fn (Builder $q) => $q->withTrashed()->whereNotNull('deleted_at'))->toSql());
    $qb_sql = mb_strtolower($query_with_permission->toSql());

    expect($manual_sql)->toContain('deleted_at');
    expect($qb_sql)->toContain('deleted_at');
    expect($manual_sql)->not->toContain('roles"."is_deleted');

    expect($query_with_permission->pluck('id')->all())->toContain($user->id);
});

it('applies nested filters on relations (AND/OR) via whereHas', function (): void {
    $role_sales = Role::factory()->create(['name' => 'sales', 'guard_name' => 'web']);
    $role_support = Role::factory()->create(['name' => 'support', 'guard_name' => 'web']);

    $u1 = User::factory()->create(['username' => 'alpha']);
    $u1->assignRole($role_sales);

    $u2 = User::factory()->create(['username' => 'beta']);
    $u2->assignRole($role_support);

    $u3 = User::factory()->create(['username' => 'gamma']);
    $u3->assignRole($role_sales);

    $filters = new FiltersGroup([
        new FiltersGroup([
            new Filter('roles.name', 'sales', FilterOperator::EQUALS),
            new Filter('username', 'alpha', FilterOperator::EQUALS),
        ], WhereClause::AND),
        new Filter('roles.name', 'support', FilterOperator::EQUALS),
    ], WhereClause::OR);

    $query = User::query();
    (new QueryBuilder())->applyFilters($query, $filters);

    $sql = mb_strtolower($query->toSql());
    $bindings = $query->getBindings();

    expect($sql)->toContain('username');
    expect($bindings)->toContain('alpha');

    // (roles.name = sales AND username = alpha) OR (roles.name = support)
    expect($query->pluck('id')->all())->toEqualCanonicalizing([$u1->id, $u2->id]);
});

it('should apply filters within eager-loaded relations (future behavior)', function (): void {
    $role_sales = Role::factory()->create(['name' => 'sales', 'guard_name' => 'web']);
    $role_support = Role::factory()->create(['name' => 'support', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole($role_sales);
    $user->assignRole($role_support);

    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.name', ColumnType::COLUMN),
        ],
        relations: ['roles'],
        filters: new FiltersGroup([
            new Filter('roles.name', 'sales', FilterOperator::EQUALS),
        ]),
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $withs = $query->getEagerLoads();
    expect($withs)->toHaveKey('roles');

    $relation = $user->roles();
    $withs['roles']($relation);
    $relation_sql = mb_strtolower($relation->getQuery()->toSql());
    $relation_bindings = $relation->getQuery()->getBindings();
    expect($relation_sql)->toContain('name');
    expect($relation_bindings)->toContain('sales');

    $result = $query->whereKey($user->id)->firstOrFail();

    expect($result->relationLoaded('roles'))->toBeTrue();
    expect($result->roles->pluck('name')->all())->toEqual(['sales']);
});

it('should force FK columns for deep relation selections (future behavior)', function (): void {
    $role = Role::factory()->create(['name' => 'sales', 'guard_name' => 'web']);
    $permission = Permission::factory()->create(['name' => 'default.orders.select', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $user = User::factory()->create();
    $user->assignRole($role);

    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.permissions.name', ColumnType::COLUMN),
        ],
        relations: [],
        filters: new FiltersGroup([
            new Filter('roles.permissions.name', 'default.orders.select', FilterOperator::EQUALS),
        ]),
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $withs = $query->getEagerLoads();
    expect($withs)->toHaveKey('roles.permissions');

    $result = $query->whereKey($user->id)->firstOrFail();
    expect($result->relationLoaded('roles'))->toBeTrue();
    expect($result->roles->first()->relationLoaded('permissions'))->toBeTrue();
    expect($result->roles->first()->permissions->pluck('name')->all())->toContain('default.orders.select');

    // Parent relation must also be constrained, otherwise unrelated roles may be eager-loaded.
    expect($result->roles->pluck('name')->all())->toEqual(['sales']);
});

it('eager-loads deep relations constrained by nested relation filters', function (): void {
    $role_sales = Role::factory()->create(['name' => 'sales', 'guard_name' => 'web']);
    $role_support = Role::factory()->create(['name' => 'support', 'guard_name' => 'web']);

    $permission_orders = Permission::factory()->create(['name' => 'default.orders.select', 'guard_name' => 'web']);
    $permission_users = Permission::factory()->create(['name' => 'default.users.select', 'guard_name' => 'web']);

    $role_sales->givePermissionTo($permission_orders);
    $role_sales->givePermissionTo($permission_users);
    $role_support->givePermissionTo($permission_users);

    $user = User::factory()->create();
    $user->assignRole($role_sales);
    $user->assignRole($role_support);

    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.permissions.name', ColumnType::COLUMN),
        ],
        filters: new FiltersGroup([
            new Filter('roles.permissions.name', 'default.orders.select', FilterOperator::EQUALS),
        ]),
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $result = $query->whereKey($user->id)->firstOrFail();
    expect($result->roles->pluck('name')->all())->toEqual(['sales']);
    expect($result->roles->first()->permissions->pluck('name')->all())->toEqual(['default.orders.select']);
});

it('should support computed columns with explicit dependencies (future behavior)', function (): void {
    if (! Schema::hasTable('qb_items')) {
        Schema::create('qb_items', function (Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('title');
            // No FK constraint: avoids teardown failures when testbench drops `users` first.
            $table->unsignedBigInteger('owner_id')->index();
            $table->timestamps();
        });
    }

    SchemaInspector::getInstance()->clearAll();

    $model = new class extends Illuminate\Database\Eloquent\Model
    {
        protected $table = 'qb_items';

        protected $guarded = [];

        public function owner(): Illuminate\Database\Eloquent\Relations\BelongsTo
        {
            return $this->belongsTo(User::class, 'owner_id');
        }

        public function getOwnerNameAttribute(): ?string
        {
            return $this->owner?->name;
        }

        /**
         * @return array<string,array{columns:array<int,string>,relations:array<int,string>}>
         */
        public function crudComputedDependencies(): array
        {
            return [
                'owner_name' => [
                    'columns' => ['owner_id'],
                    'relations' => ['owner'],
                ],
            ];
        }
    };

    $owner = User::factory()->create(['name' => 'Mario Rossi']);
    $model->forceFill(['title' => 'Test', 'owner_id' => $owner->id])->save();

    $query = $model->newQuery();
    $request_data = qb_make_list_request_data([
        new Column('qb_items.title', ColumnType::COLUMN),
        new Column('qb_items.owner_name', ColumnType::APPEND),
    ]);

    (new QueryBuilder())->prepareQuery($query, $request_data);

    /** @var array<int,string>|null $selected */
    $selected = $query->getQuery()->columns;

    expect($selected)->not->toBeNull();
    expect($selected)->not->toContain('qb_items.*');
    expect($selected)->toContain('id');
    expect($selected)->toContain('title');
    expect($selected)->toContain('owner_id');

    expect($query->getEagerLoads())->toHaveKey('owner');

    $result = $query->firstOrFail();
    expect($result->owner_name)->toBe('Mario Rossi');
});

it('prepareQuery orders by fully qualified main column when sort uses table prefix', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [new Column('users.username', ColumnType::COLUMN)],
        sort: [new Sort('users.username', SortDirection::DESC)],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $orders = $query->getQuery()->orders ?? [];

    expect($orders)->not->toBeEmpty();
    expect($orders[0]['column'])->toBe('users.username');
    expect(mb_strtolower((string) $orders[0]['direction']))->toBe('desc');
});

it('prepareQuery normalizes relations from associative array entries', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.name', ColumnType::COLUMN),
        ],
        relations: [['name' => 'roles']],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    expect($query->getEagerLoads())->toHaveKey('roles');
});

it('prepareQuery applies withSum aggregate on relation column', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.id', ColumnType::SUM),
        ],
        relations: ['roles'],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $sql = mb_strtolower($query->toSql());

    expect($sql)->toContain('sum');
    expect($sql)->toContain('roles');
});

it('prepareQuery applies descending sort on relation eager load', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.name', ColumnType::COLUMN),
        ],
        relations: ['roles'],
        sort: [new Sort('roles.name', SortDirection::DESC)],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $user = User::factory()->create();
    $relation = $user->roles();
    $query->getEagerLoads()['roles']($relation);

    $orders = $relation->getQuery()->getQuery()->orders ?? [];

    expect($orders)->not->toBeEmpty();
    expect($orders[0]['column'])->toBe('name');
    expect(mb_strtolower((string) $orders[0]['direction']))->toBe('desc');
});

it('prepareQuery applies withAvg aggregate on relation column', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.id', ColumnType::AVG),
        ],
        relations: ['roles'],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $sql = mb_strtolower($query->toSql());

    expect($sql)->toContain('avg');
    expect($sql)->toContain('roles');
});

it('prepareQuery applies withMax aggregate on relation column', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.id', ColumnType::MAX),
        ],
        relations: ['roles'],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $sql = mb_strtolower($query->toSql());

    expect($sql)->toContain('max');
    expect($sql)->toContain('roles');
});

it('prepareQuery applies withMin aggregate on nested relation permissions id', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.name', ColumnType::COLUMN),
            new Column('users.roles.permissions.id', ColumnType::MIN),
        ],
        relations: ['roles'],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $user = User::factory()->create();
    $relation = $user->roles();
    $query->getEagerLoads()['roles']($relation);

    $sql = mb_strtolower($relation->getQuery()->toSql());

    expect($sql)->toContain('min');
    expect($sql)->toContain('permissions');
});

it('prepareQuery ignores blacklisted relations from request payload', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [
            new Column('users.username', ColumnType::COLUMN),
            new Column('users.roles.name', ColumnType::COLUMN),
        ],
        relations: ['children', 'roles'],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    $withs = $query->getEagerLoads();

    expect($withs)->toHaveKey('roles');
    expect($withs)->not->toHaveKey('children');
});

it('prepareQuery ignores relation sort without field segment', function (): void {
    $query = User::query();
    $request_data = qb_make_list_request_data(
        columns: [new Column('users.username', ColumnType::COLUMN)],
        sort: [new Sort('roles', SortDirection::ASC)],
    );

    (new QueryBuilder())->prepareQuery($query, $request_data);

    expect($query->getQuery()->orders ?? [])->toBe([]);
});
