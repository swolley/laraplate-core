<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Core\Casts\Column;
use Modules\Core\Casts\ColumnType;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\Sort;
use Modules\Core\Casts\SortDirection;
use Modules\Core\Models\Role;
use App\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\CrudService;
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

it('list returns paginated results when page is set', function (): void {
    $superadmin = crud_login_as_superadmin();

    User::factory()->count(30)->create();

    $service = new CrudService(app(AuthorizationService::class), new QueryBuilder());
    $request = crud_make_validated_request();
    $request->setUserResolver(fn () => $superadmin);
    $request_data = crud_make_list_request_data(new User(), $request, [
        new Column('users.id', ColumnType::COLUMN),
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
        new Column('users.id', ColumnType::COLUMN),
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
        new Column('users.id', ColumnType::COLUMN),
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
        new Column('users.username', ColumnType::COLUMN),
        new Column('users.roles.name', ColumnType::COLUMN),
    ]);

    crud_set_request_data_prop($request_data, 'relations', ['roles']);
    crud_set_request_data_prop($request_data, 'sort', [new Sort('username', SortDirection::DESC)]);
    crud_set_request_data_prop($request_data, 'filters', new FiltersGroup([
        new Filter('roles.name', 'sales', FilterOperator::EQUALS),
    ]));

    $result = $service->list($request_data);

    expect($result->data->pluck('id')->all())->toEqual([$u_beta->id, $u_alpha->id]);
    expect($result->data->first()->relationLoaded('roles'))->toBeTrue();
    expect($result->data->first()->roles->pluck('name')->all())->toEqual(['sales']);
    expect($result->data->last()->roles->pluck('name')->all())->toEqual(['sales']);
    expect($result->data->pluck('id')->all())->not->toContain($u_gamma->id);
});
