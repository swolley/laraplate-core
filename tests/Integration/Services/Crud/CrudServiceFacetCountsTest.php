<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Core\Casts\Column;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Services\Crud\CrudService;

/**
 * @param  array<string,mixed>  $validated
 */
function facet_make_request(array $validated = []): Request
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
 * @param  array<int,Column>  $columns
 */
function facet_make_list_data(Model $model, Request $request, array $columns): Modules\Core\Casts\ListRequestData
{
    $data = new ReflectionClass(Modules\Core\Casts\ListRequestData::class)->newInstanceWithoutConstructor();

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

function facet_login_superadmin(): User
{
    $role = Role::factory()->create([
        'name' => config('permission.roles.superadmin'),
        'guard_name' => 'web',
    ]);

    $user = User::factory()->create(['name' => 'zzz-superadmin']);
    $user->assignRole($role);

    auth()->login($user);

    return $user;
}

it('counts each facet value with total equal to count when there is no base filter', function (): void {
    $superadmin = facet_login_superadmin();

    User::factory()->create(['name' => 'A']);
    User::factory()->create(['name' => 'A']);
    User::factory()->create(['name' => 'B']);

    $service = app(CrudService::class);
    $request = facet_make_request();
    $request->setUserResolver(fn (): User => $superadmin);
    $base = facet_make_list_data(new User, $request, [new Column('name')]);

    $facets = $service->facetCounts($base);

    $by_value = collect($facets['name'])->keyBy('value');

    expect($by_value['A']['total'])->toBe(2)
        ->and($by_value['A']['count'])->toBe(2)
        ->and($by_value['B']['total'])->toBe(1)
        ->and($by_value['B']['count'])->toBe(1);
});

it('keeps total unfiltered while count reflects a base filter on another field', function (): void {
    $superadmin = facet_login_superadmin();

    User::factory()->create(['name' => 'A', 'email' => 'a-keep@example.test']);
    User::factory()->create(['name' => 'A', 'email' => 'a-drop@example.test']);
    User::factory()->create(['name' => 'B', 'email' => 'b-keep@example.test']);

    $service = app(CrudService::class);
    $request = facet_make_request();
    $request->setUserResolver(fn (): User => $superadmin);
    $base = facet_make_list_data(new User, $request, [new Column('name')]);

    $filters = new ReflectionProperty($base, 'filters');
    $filters->setValue($base, new FiltersGroup([new Filter('email', '%keep%', FilterOperator::Like)]));

    $facets = $service->facetCounts($base);

    $by_value = collect($facets['name'])->keyBy('value');

    // total ignores the base filter; count applies it.
    expect($by_value['A']['total'])->toBe(2)
        ->and($by_value['A']['count'])->toBe(1)
        ->and($by_value['B']['total'])->toBe(1)
        ->and($by_value['B']['count'])->toBe(1);
});

it('excludes the facet field own filter so its other values stay counted', function (): void {
    $superadmin = facet_login_superadmin();

    User::factory()->create(['name' => 'A', 'email' => 'a-keep@example.test']);
    User::factory()->create(['name' => 'A', 'email' => 'a-drop@example.test']);
    User::factory()->create(['name' => 'B', 'email' => 'b-keep@example.test']);

    $service = app(CrudService::class);
    $request = facet_make_request();
    $request->setUserResolver(fn (): User => $superadmin);
    $base = facet_make_list_data(new User, $request, [new Column('name')]);

    // The facet field (name) is itself selected, alongside another filter.
    $filters = new ReflectionProperty($base, 'filters');
    $filters->setValue($base, new FiltersGroup([
        new Filter('name', 'A', FilterOperator::Equals),
        new Filter('email', '%keep%', FilterOperator::Like),
    ]));

    $facets = $service->facetCounts($base);
    $by_value = collect($facets['name'])->keyBy('value');

    // 'name = A' is dropped for the name facet; 'email like keep' stays applied.
    expect($by_value['A']['count'])->toBe(1)
        ->and($by_value['B']['count'])->toBe(1)
        ->and($by_value['A']['total'])->toBe(2)
        ->and($by_value['B']['total'])->toBe(1);
});

it('restricts facet counts to the ACL-visible rows', function (): void {
    $permission = Permission::findOrCreate('default.users.select', 'web');

    $role = Role::factory()->create(['name' => 'facet_acl_role_' . uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo($permission);

    $acl = new ACL;
    $acl->setSkipValidation(true);
    $acl->forceFill([
        'permission_id' => $permission->id,
        'filters' => new FiltersGroup([new Filter('name', 'A', FilterOperator::Equals)], WhereClause::And),
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    $viewer = User::factory()->create(['name' => 'zzz-viewer']);
    $viewer->assignRole($role);
    auth()->login($viewer);

    User::factory()->create(['name' => 'A']);
    User::factory()->create(['name' => 'A']);
    User::factory()->create(['name' => 'B']);

    $service = app(CrudService::class);
    $request = facet_make_request();
    $request->setUserResolver(fn (): User => $viewer);
    $base = facet_make_list_data(new User, $request, [new Column('name')]);

    $facets = $service->facetCounts($base);
    $by_value = collect($facets['name'])->keyBy('value');

    // ACL limits visibility to name = A: rows named B (and the viewer) are hidden,
    // so 'B' never appears in the distribution and A only counts visible rows.
    expect($by_value->has('B'))->toBeFalse()
        ->and($by_value['A']['total'])->toBe(2)
        ->and($by_value['A']['count'])->toBe(2);
});
