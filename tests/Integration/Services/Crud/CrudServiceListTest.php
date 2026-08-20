<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Core\Authorization\RetrievedSelectGuard;
use Modules\Core\Casts\Column;
use Modules\Core\Casts\ColumnType;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\Sort;
use Modules\Core\Casts\SortDirection;
use Modules\Core\Models\Role;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\CrudService;
use Modules\Core\Services\Crud\DTOs\PaginationMode;
use Modules\Core\Services\Crud\QueryBuilder;

/**
 * @param  array<string,mixed>  $validated
 */
function crud_make_validated_request(array $validated = []): Request
{
    return new class($validated) extends Request
    {
        /**
         * @param  array<string,mixed>  $validated
         */
        public function __construct(private readonly array $validated)
        {
            parent::__construct();
        }

        public function validated(?string $key = null, mixed $default = null): mixed
        {
            if ($key === null) {
                return $this->validated;
            }

            return $this->validated[$key] ?? $default;
        }
    };
}

/**
 * Build a ListRequestData without invoking its constructor (we don't want DynamicEntity resolution here).
 *
 * @param  array<int,Column>  $columns
 */
function crud_make_list_request_data(Model $model, Request $request, array $columns): ListRequestData
{
    $ref = new ReflectionClass(ListRequestData::class);

    /** @var ListRequestData $data */
    $data = $ref->newInstanceWithoutConstructor();

    $set = static function (object $obj, string $prop, mixed $value): void {
        $p = new ReflectionProperty($obj, $prop);
        $p->setAccessible(true);
        $p->setValue($obj, $value);
    };

    $set($data, 'request', $request);
    $set($data, 'mainEntity', $model->getTable());
    $set($data, 'primaryKey', $model->getKeyName());
    $set($data, 'connection', $model->getConnectionName());
    $set($data, 'model', $model);
    $set($data, 'columns', $columns);
    $set($data, 'relations', []);
    $set($data, 'sort', []);
    $set($data, 'filters', null);
    $set($data, 'group_by', []);

    $set($data, 'pagination', 25);
    $set($data, 'page', null);
    $set($data, 'skip', null);
    $set($data, 'take', null);
    $set($data, 'from', null);
    $set($data, 'to', null);
    $set($data, 'limit', null);
    $set($data, 'count', false);

    return $data;
}

function crud_login_as_superadmin(): User
{
    $superadmin_role = Role::factory()->create([
        'name' => config('permission.roles.superadmin'),
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create();
    $user->assignRole($superadmin_role);

    auth()->login($user);

    return $user;
}

function crud_set_request_data_prop(ListRequestData $request_data, string $name, mixed $value): void
{
    $prop = new ReflectionProperty($request_data, $name);
    $prop->setAccessible(true);
    $prop->setValue($request_data, $value);
}

it('suppresses the redundant per-row select check for the main model during list hydration', function (): void {
    $superadmin = crud_login_as_superadmin();

    User::factory()->count(3)->create();

    // Capture whether the guard is engaged for each User hydrated by the list query.
    $captured = [];
    User::retrieved(function (User $user) use (&$captured): void {
        $captured[] = RetrievedSelectGuard::isSuppressed(User::class);
    });

    $service = new CrudService(app(AuthorizationService::class), new QueryBuilder());
    $request = crud_make_validated_request();
    $request->setUserResolver(fn () => $superadmin);
    $request_data = crud_make_list_request_data(new User(), $request, [
        new Column('users.id', ColumnType::Column),
    ]);

    $result = $service->list($request_data);

    // The list still hydrates rows, they were hydrated while the guard was active,
    // and the suppression is fully released once the list call returns.
    expect($result->data->count())->toBeGreaterThan(0)
        ->and(in_array(true, $captured, true))->toBeTrue()
        ->and(RetrievedSelectGuard::isSuppressed(User::class))->toBeFalse();
});

it('look-ahead pagination reports hasMore without a COUNT and skips the total', function (): void {
    $superadmin = crud_login_as_superadmin();

    User::factory()->count(30)->create();

    $service = new CrudService(app(AuthorizationService::class), new QueryBuilder());
    $request = crud_make_validated_request();
    $request->setUserResolver(fn () => $superadmin);
    $request_data = crud_make_list_request_data(new User(), $request, [
        new Column('users.id', ColumnType::Column),
    ]);

    foreach (['page' => 1, 'pagination' => 10, 'skip' => 0, 'take' => 10, 'from' => 1, 'to' => 10, 'totals' => false] as $prop => $value) {
        crud_set_request_data_prop($request_data, $prop, $value);
    }

    $connection = (new User())->getConnectionName();
    DB::connection($connection)->flushQueryLog();
    DB::connection($connection)->enableQueryLog();

    $result = $service->list($request_data);

    $queries = DB::connection($connection)->getQueryLog();
    DB::connection($connection)->disableQueryLog();

    $count_queries = collect($queries)->filter(
        fn (array $entry): bool => preg_match('/select count\(\*\)/i', (string) $entry['query']) === 1,
    );

    // No COUNT(*) is issued, the page is trimmed back to its size, and meta reports
    // hasMore in place of the (skipped) exact total.
    expect($count_queries)->toBeEmpty()
        ->and($result->data->count())->toBe(10)
        ->and($result->meta->hasMore)->toBeTrue()
        ->and($result->meta->mode)->toBe(PaginationMode::Lookahead)
        ->and($result->meta->totalRecords)->toBeNull()
        ->and($result->meta->totalPages)->toBeNull();
});

it('look-ahead pagination reports hasMore false on the last page', function (): void {
    $superadmin = crud_login_as_superadmin();

    // 30 factory users + the superadmin = 31 visible rows.
    User::factory()->count(30)->create();

    $service = new CrudService(app(AuthorizationService::class), new QueryBuilder());
    $request = crud_make_validated_request();
    $request->setUserResolver(fn () => $superadmin);
    $request_data = crud_make_list_request_data(new User(), $request, [
        new Column('users.id', ColumnType::Column),
    ]);

    // Page 4 of 10 spans rows 31..40, so only the 31st row exists: no further page.
    foreach (['page' => 4, 'pagination' => 10, 'skip' => 30, 'take' => 10, 'from' => 31, 'to' => 40, 'totals' => false] as $prop => $value) {
        crud_set_request_data_prop($request_data, $prop, $value);
    }

    $result = $service->list($request_data);

    expect($result->data->count())->toBe(1)
        ->and($result->meta->hasMore)->toBeFalse()
        ->and($result->meta->mode)->toBe(PaginationMode::Lookahead)
        ->and($result->meta->totalRecords)->toBeNull();
});

it('totals=true computes the exact total and reports the counted mode', function (): void {
    $superadmin = crud_login_as_superadmin();

    User::factory()->count(30)->create();

    $service = new CrudService(app(AuthorizationService::class), new QueryBuilder());
    $request = crud_make_validated_request();
    $request->setUserResolver(fn () => $superadmin);
    $request_data = crud_make_list_request_data(new User(), $request, [
        new Column('users.id', ColumnType::Column),
    ]);

    foreach (['page' => 1, 'pagination' => 10, 'skip' => 0, 'take' => 10, 'from' => 1, 'to' => 10, 'totals' => true] as $prop => $value) {
        crud_set_request_data_prop($request_data, $prop, $value);
    }

    $result = $service->list($request_data);

    // Exact count preserved, counted mode advertised, hasMore stays null.
    expect($result->meta->totalRecords)->toBe(31)
        ->and($result->meta->totalPages)->toBe(4)
        ->and($result->meta->mode)->toBe(PaginationMode::Counted)
        ->and($result->meta->hasMore)->toBeNull();
});

it('list returns paginated results when page is set', function (): void {
    $superadmin = crud_login_as_superadmin();

    User::factory()->count(30)->create();

    $service = new CrudService(app(AuthorizationService::class), new QueryBuilder());
    $request = crud_make_validated_request();
    $request->setUserResolver(fn () => $superadmin);
    $request_data = crud_make_list_request_data(new User(), $request, [
        new Column('users.id', ColumnType::Column),
    ]);

    $ref = new ReflectionProperty($request_data, 'page');
    $ref->setAccessible(true);
    $ref->setValue($request_data, 2);

    $ref = new ReflectionProperty($request_data, 'pagination');
    $ref->setAccessible(true);
    $ref->setValue($request_data, 10);

    $ref = new ReflectionProperty($request_data, 'skip');
    $ref->setAccessible(true);
    $ref->setValue($request_data, 10);

    $ref = new ReflectionProperty($request_data, 'take');
    $ref->setAccessible(true);
    $ref->setValue($request_data, 10);

    $ref = new ReflectionProperty($request_data, 'from');
    $ref->setAccessible(true);
    $ref->setValue($request_data, 11);

    $ref = new ReflectionProperty($request_data, 'to');
    $ref->setAccessible(true);
    $ref->setValue($request_data, 20);

    $result = $service->list($request_data);

    expect($result->data)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
    expect($result->data->count())->toBe(10);
    expect($result->meta->currentPage)->toBe(2);
    expect($result->meta->totalPages)->toBeGreaterThanOrEqual(3);
});

it('list returns results for from/to range when set', function (): void {
    $superadmin = crud_login_as_superadmin();

    User::factory()->count(10)->create();

    $service = new CrudService(app(AuthorizationService::class), new QueryBuilder());
    $request = crud_make_validated_request();
    $request->setUserResolver(fn () => $superadmin);
    $request_data = crud_make_list_request_data(new User(), $request, [
        new Column('users.id', ColumnType::Column),
    ]);

    (new ReflectionProperty($request_data, 'from'))->setValue($request_data, 3);
    (new ReflectionProperty($request_data, 'to'))->setValue($request_data, 6);
    (new ReflectionProperty($request_data, 'skip'))->setValue($request_data, 2);
    (new ReflectionProperty($request_data, 'take'))->setValue($request_data, 3);
    (new ReflectionProperty($request_data, 'pagination'))->setValue($request_data, 3);

    $result = $service->list($request_data);

    expect($result->data)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
    expect($result->data->count())->toBe(4);
    expect($result->meta->from)->toBe(3);
    expect($result->meta->to)->toBe(6);
});

it('list returns limited results when limit is set (no page/from)', function (): void {
    $superadmin = crud_login_as_superadmin();

    User::factory()->count(50)->create();

    $service = new CrudService(app(AuthorizationService::class), new QueryBuilder());
    $request = crud_make_validated_request();
    $request->setUserResolver(fn () => $superadmin);
    $request_data = crud_make_list_request_data(new User(), $request, [
        new Column('users.id', ColumnType::Column),
    ]);

    (new ReflectionProperty($request_data, 'limit'))->setValue($request_data, 7);
    (new ReflectionProperty($request_data, 'take'))->setValue($request_data, 7);

    $result = $service->list($request_data);

    expect($result->data)->toBeInstanceOf(Illuminate\Database\Eloquent\Collection::class);
    expect($result->data->count())->toBe(7);
    expect($result->meta->currentPage)->toBeNull();
});

it('list applies request filters sort and relation eager-load consistently', function (): void {
    $superadmin = crud_login_as_superadmin();

    $role_sales = Role::factory()->create(['name' => 'sales', 'guard_name' => 'web']);
    $role_support = Role::factory()->create(['name' => 'support', 'guard_name' => 'web']);

    $u_alpha = User::factory()->create(['username' => 'qb_list_alpha']);
    $u_alpha->assignRole($role_sales);

    $u_beta = User::factory()->create(['username' => 'qb_list_beta']);
    $u_beta->assignRole($role_sales);
    $u_beta->assignRole($role_support);

    $u_gamma = User::factory()->create(['username' => 'qb_list_gamma']);
    $u_gamma->assignRole($role_support);

    $service = new CrudService(app(AuthorizationService::class), new QueryBuilder());
    $request = crud_make_validated_request();
    $request->setUserResolver(fn () => $superadmin);

    $request_data = crud_make_list_request_data(new User(), $request, [
        new Column('users.username', ColumnType::Column),
        new Column('users.roles.name', ColumnType::Column),
    ]);

    crud_set_request_data_prop($request_data, 'relations', ['roles']);
    crud_set_request_data_prop($request_data, 'sort', [new Sort('username', SortDirection::Desc)]);
    crud_set_request_data_prop($request_data, 'filters', new FiltersGroup([
        new Filter('roles.name', 'sales', FilterOperator::Equals),
    ]));

    $result = $service->list($request_data);

    expect($result->data->pluck('id')->all())->toEqual([$u_beta->id, $u_alpha->id]);
    expect($result->data->first()->relationLoaded('roles'))->toBeTrue();
    expect($result->data->first()->roles->pluck('name')->all())->toEqual(['sales']);
    expect($result->data->last()->roles->pluck('name')->all())->toEqual(['sales']);
    expect($result->data->pluck('id')->all())->not->toContain($u_gamma->id);
});

it('list skips the redundant count query when the full result set is materialized', function (): void {
    $superadmin = crud_login_as_superadmin();

    User::factory()->count(3)->create();
    $expected_total = User::query()->count();

    $service = new CrudService(app(AuthorizationService::class), new QueryBuilder());
    $request = crud_make_validated_request();
    $request->setUserResolver(fn () => $superadmin);
    $request_data = crud_make_list_request_data(new User(), $request, [
        new Column('users.id', ColumnType::Column),
    ]);

    $count_queries = 0;
    DB::listen(static function (Illuminate\Database\Events\QueryExecuted $query) use (&$count_queries): void {
        if (str_contains($query->sql, 'count(*)') && str_contains($query->sql, '"users"')) {
            $count_queries++;
        }
    });

    $result = $service->list($request_data);

    expect($result->data->count())->toBe($expected_total)
        ->and($result->meta->totalRecords)->toBe($expected_total)
        ->and($result->meta->currentRecords)->toBe($expected_total)
        ->and($count_queries)->toBe(0);
});

it('list still issues the count query when a limit caps the result set', function (): void {
    $superadmin = crud_login_as_superadmin();

    User::factory()->count(9)->create();
    $expected_total = User::query()->count();

    $service = new CrudService(app(AuthorizationService::class), new QueryBuilder());
    $request = crud_make_validated_request();
    $request->setUserResolver(fn () => $superadmin);
    $request_data = crud_make_list_request_data(new User(), $request, [
        new Column('users.id', ColumnType::Column),
    ]);

    (new ReflectionProperty($request_data, 'limit'))->setValue($request_data, 4);
    (new ReflectionProperty($request_data, 'take'))->setValue($request_data, 4);

    $count_queries = 0;
    DB::listen(static function (Illuminate\Database\Events\QueryExecuted $query) use (&$count_queries): void {
        if (str_contains($query->sql, 'count(*)') && str_contains($query->sql, '"users"')) {
            $count_queries++;
        }
    });

    $result = $service->list($request_data);

    expect($result->data->count())->toBe(4)
        ->and($result->meta->totalRecords)->toBe($expected_total)
        ->and($count_queries)->toBe(1);
});

it('reports currentRecords as the number of records even when group_by collapses them', function (): void {
    $superadmin = crud_login_as_superadmin();

    // Every user shares the same name so grouping yields a single bucket.
    User::factory()->count(3)->create(['name' => 'Shared Name']);
    $expected_total = User::query()->where('name', 'Shared Name')->count();

    $service = new CrudService(app(AuthorizationService::class), new QueryBuilder());
    $request = crud_make_validated_request();
    $request->setUserResolver(fn () => $superadmin);
    $request_data = crud_make_list_request_data(new User(), $request, [
        new Column('users.id', ColumnType::Column),
        new Column('users.name', ColumnType::Column),
    ]);

    crud_set_request_data_prop($request_data, 'filters', new FiltersGroup(
        filters: [new Filter('users.name', 'Shared Name', FilterOperator::Equals)],
        operator: Modules\Core\Casts\WhereClause::And,
    ));
    crud_set_request_data_prop($request_data, 'group_by', ['name']);

    $result = $service->list($request_data);

    // The grouped payload has a single bucket, but the counters must reflect records.
    expect($result->data)->toHaveCount(1)
        ->and($result->meta->totalRecords)->toBe($expected_total)
        ->and($result->meta->currentRecords)->toBe($expected_total);
});
