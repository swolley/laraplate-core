<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Core\Casts\Column;
use Modules\Core\Casts\ColumnType;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User as CoreUser;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\CrudService;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\Core\Support\CrudApiExposure;
use Modules\Core\Tests\Stubs\Crud\ComputedMethodUserStub;

/*
 * A read request never makes a model run a method it did not declare as computable for the CRUD: a `method`
 * column, an appended attribute, a group_by key, a facet field or a graph relation named after a model method
 * (delete, forceDelete, save...) is refused with a client error before anything is called, and no row changes.
 */

beforeEach(function (): void {
    Cache::flush();
    CrudApiExposure::enable();
});

/**
 * A reader holding every read and write permission of the users table on the `api` guard, so that a
 * mutation reached through a read is not stopped by the model events.
 */
function crudcall_reader(): User
{
    $role = Role::factory()->create(['name' => 'crudcall_' . uniqid(), 'guard_name' => 'api']);

    foreach (['select', 'insert', 'update', 'delete', 'forceDelete', 'restore'] as $operation) {
        $role->givePermissionTo(Permission::query()->firstOrCreate(['name' => 'default.users.' . $operation, 'guard_name' => 'api']));
    }

    $user = User::query()->findOrFail(CoreUser::factory()->create()->getKey());
    $user->assignRole($role);

    return $user->fresh();
}

/**
 * @return list<array<string, mixed>>
 */
function crudcall_users_snapshot(): array
{
    return DB::table('users')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();
}

describe('a model method named by a read request', function (): void {
    it('is refused as a method column and changes no row', function (string $name, string $action): void {
        $reader = crudcall_reader();
        $other = CoreUser::factory()->create();
        $before = crudcall_users_snapshot();

        $query = ['columns' => [['name' => $name, 'type' => 'method']]] + ($action === 'detail' ? ['id' => $other->id] : []);
        $response = $this->actingAs($reader)->getJson(sprintf('/api/v1/%s/core/users?%s', $action, http_build_query($query)));

        expect(crudcall_users_snapshot())->toBe($before)
            ->and($response->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
    })->with(['delete', 'forceDelete', 'truncate', 'update', 'save', 'touch'])->with(['select', 'detail']);

    it('is refused as a method column on a related record and changes no row', function (): void {
        $reader = crudcall_reader();
        Permission::query()->firstOrCreate(['name' => 'default.vend_roles.select', 'guard_name' => 'api']);
        $before = crudcall_users_snapshot();

        $response = $this->actingAs($reader)->getJson('/api/v1/select/core/users?' . http_build_query(['columns' => [['name' => 'roles.forceDelete', 'type' => 'method']]]));

        expect(crudcall_users_snapshot())->toBe($before)
            ->and($response->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
    });

    it('is refused as an appended attribute and changes no row', function (string $name): void {
        $reader = crudcall_reader();
        $before = crudcall_users_snapshot();

        $response = $this->actingAs($reader)->getJson('/api/v1/select/core/users?' . http_build_query(['columns' => [['name' => $name, 'type' => 'append']]]));

        expect(crudcall_users_snapshot())->toBe($before)
            ->and($response->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
    })->with(['delete', 'forceDelete', 'touch']);

    it('is refused as a group_by key and changes no row', function (string $name): void {
        $reader = crudcall_reader();
        CoreUser::factory()->create();
        $before = crudcall_users_snapshot();

        $response = $this->actingAs($reader)->getJson('/api/v1/select/core/users?' . http_build_query(['group_by' => [$name]]));

        expect(crudcall_users_snapshot())->toBe($before)
            ->and($response->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
    })->with(['forceDelete', 'restore', 'delete', 'touch']);

    it('is refused as a facet field and changes no row', function (string $name): void {
        $reader = crudcall_reader();
        CoreUser::factory()->create();
        $before = crudcall_users_snapshot();

        $response = $this->actingAs($reader)->getJson('/api/v1/facets/core/users?' . http_build_query(['columns' => [$name]]));

        expect(crudcall_users_snapshot())->toBe($before)
            ->and($response->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
    })->with(['forceDelete', 'restore']);

    it('is refused as a facet relation or a facet group and changes no row', function (array $facet): void {
        $reader = crudcall_reader();
        CoreUser::factory()->create();
        $before = crudcall_users_snapshot();

        $response = $this->actingAs($reader)->getJson('/api/v1/facets/core/users?' . http_build_query(['facet' => $facet]));

        expect(crudcall_users_snapshot())->toBe($before)
            ->and($response->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
    })->with([
        'group through a method' => [['groupBy' => 'save.name']],
        'relation named after a method' => [['relation' => 'save', 'groupBy' => 'id']],
        'group through forceDelete' => [['groupBy' => 'forceDelete.name']],
    ]);

    it('is refused as a graph relation and changes no row', function (string $name): void {
        $reader = crudcall_reader();
        $center = CoreUser::factory()->create();
        $before = crudcall_users_snapshot();

        $response = $this->actingAs($reader)->getJson(sprintf('/api/v1/crud/graph/expand/core/users/%d?%s', $center->id, http_build_query(['relations' => [$name], 'depth' => 1])));

        expect(crudcall_users_snapshot())->toBe($before)
            ->and($response->status())->toBeGreaterThanOrEqual(400)->toBeLessThan(500);
    })->with(['delete', 'forceDelete', 'touch']);
});

it('computes a method the model declares as computable for the CRUD', function (): void {
    $superadmin = User::query()->findOrFail(CoreUser::factory()->create(['name' => 'ada'])->getKey());
    $superadmin->assignRole(Role::factory()->create(['name' => config('permission.roles.superadmin'), 'guard_name' => 'web']));
    auth()->login($superadmin);

    $model = new ComputedMethodUserStub;
    $request = Request::create('/api/v1/select/core/users');
    $request->setUserResolver(static fn (): User => $superadmin);

    $data = (new ReflectionClass(ListRequestData::class))->newInstanceWithoutConstructor();

    foreach ([
        'request' => $request,
        'mainEntity' => 'users',
        'primaryKey' => 'id',
        'model' => $model,
        'connection' => null,
        'columns' => [new Column('users.id'), new Column('users.shoutedName', ColumnType::Method)],
        'relations' => [],
        'sort' => [],
        'filters' => null,
        'group_by' => [],
        'page' => null,
        'from' => null,
        'to' => null,
        'limit' => 25,
        'pagination' => 25,
        'count' => false,
    ] as $property => $value) {
        (new ReflectionProperty($data, $property))->setValue($data, $value);
    }

    $result = (new CrudService(app(AuthorizationService::class), app(QueryBuilder::class)))->list($data);
    $row = $result->data->firstWhere('id', $superadmin->id);

    expect($row->getAttribute('shoutedName'))->toBe('ADA');
});
