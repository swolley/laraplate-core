<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\ModifyRequestData;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\CrudService;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\Core\Tests\Fixtures\CrudServiceTestSingleRelParent;
use Modules\Core\Tests\Fixtures\CrudSyncRelChild;
use Modules\Core\Tests\Fixtures\CrudSyncRelParent;

function sync_rel_login_superadmin(): User
{
    $role = Role::factory()->create(['name' => config('permission.roles.superadmin'), 'guard_name' => 'web']);
    $user = User::factory()->create(['username' => 'sync_rel_' . uniqid(), 'email' => 'sync_rel_' . uniqid() . '@example.com']);
    $user->assignRole($role);
    auth()->login($user);

    return $user;
}

/**
 * @param  array<string, mixed>  $changes
 * @param  array<string, list<int>>  $relations
 */
function sync_rel_modify_data(object $model, Request $request, array $changes, array $relations): ModifyRequestData
{
    $ref = new ReflectionClass(ModifyRequestData::class);
    $data = $ref->newInstanceWithoutConstructor();

    foreach ([
        'request' => $request,
        'mainEntity' => $model->getTable(),
        'primaryKey' => 'id',
        'model' => $model,
        'connection' => $model->getConnectionName(),
        'changes' => $changes,
        'relations' => $relations,
    ] as $property => $value) {
        $p = new ReflectionProperty(ModifyRequestData::class, $property);
        $p->setAccessible(true);
        $p->setValue($data, $value);
    }

    return $data;
}

function sync_rel_request(int $id, User $user): Request
{
    $request = Request::create('/modify', 'PATCH', ['id' => $id]);
    $request->request->set('id', $id);
    $request->setUserResolver(fn () => $user);

    return $request;
}

beforeEach(function (): void {
    Schema::create('crud_sync_rel_parent', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
    });
    Schema::create('crud_sync_rel_child', function (Blueprint $table): void {
        $table->id();
    });
    Schema::create('crud_sync_rel_pivot', function (Blueprint $table): void {
        $table->unsignedBigInteger('parent_id');
        $table->unsignedBigInteger('child_id');
        $table->primary(['parent_id', 'child_id']);
    });

    $this->service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));
    $this->admin = sync_rel_login_superadmin();

    $this->parent = CrudSyncRelParent::query()->create(['name' => 'root']);
    $this->children = collect(range(1, 3))->map(fn (): CrudSyncRelChild => CrudSyncRelChild::query()->create());
});

afterEach(function (): void {
    Schema::dropIfExists('crud_sync_rel_pivot');
    Schema::dropIfExists('crud_sync_rel_child');
    Schema::dropIfExists('crud_sync_rel_parent');
});

it('syncs a whitelisted many-to-many relation from a list of ids', function (): void {
    $a = $this->children[0]->id;
    $b = $this->children[1]->id;

    $data = sync_rel_modify_data($this->parent, sync_rel_request($this->parent->id, $this->admin), [], ['children' => [$a, $b]]);
    $this->service->update($data);

    expect($this->parent->children()->pluck('crud_sync_rel_child.id')->sort()->values()->all())->toBe([$a, $b]);
});

it('replaces an existing relation set wholesale', function (): void {
    $this->parent->children()->sync([$this->children[0]->id]);
    $target = $this->children[2]->id;

    $data = sync_rel_modify_data($this->parent, sync_rel_request($this->parent->id, $this->admin), [], ['children' => [$target]]);
    $this->service->update($data);

    expect($this->parent->children()->pluck('crud_sync_rel_child.id')->all())->toBe([$target]);
});

it('clears a relation on an empty array', function (): void {
    $this->parent->children()->sync([$this->children[0]->id, $this->children[1]->id]);

    $data = sync_rel_modify_data($this->parent, sync_rel_request($this->parent->id, $this->admin), [], ['children' => []]);
    $this->service->update($data);

    expect($this->parent->children()->count())->toBe(0);
});

it('leaves relations untouched when the payload has none and still updates columns', function (): void {
    $this->parent->children()->sync([$this->children[0]->id]);

    $data = sync_rel_modify_data($this->parent, sync_rel_request($this->parent->id, $this->admin), ['name' => 'renamed'], []);
    $this->service->update($data);

    expect($this->parent->fresh()->name)->toBe('renamed')
        ->and($this->parent->children()->pluck('crud_sync_rel_child.id')->all())->toBe([$this->children[0]->id]);
});

it('rejects a relation that is not whitelisted', function (): void {
    $data = sync_rel_modify_data($this->parent, sync_rel_request($this->parent->id, $this->admin), [], ['secrets' => [1]]);

    expect(fn () => $this->service->update($data))->toThrow(UnexpectedValueException::class);
});

it('rejects a whitelisted relation that is not many-to-many', function (): void {
    $data = sync_rel_modify_data($this->parent, sync_rel_request($this->parent->id, $this->admin), [], ['offspring' => [1]]);

    expect(fn () => $this->service->update($data))->toThrow(UnexpectedValueException::class);
});

it('rejects any relation payload on a model that does not opt in', function (): void {
    Schema::create('crud_single_rel_parent', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });
    $plain = CrudServiceTestSingleRelParent::query()->create(['name' => 'x']);

    $data = sync_rel_modify_data($plain, sync_rel_request($plain->id, $this->admin), [], ['childRecord' => [1]]);

    expect(fn () => $this->service->update($data))->toThrow(UnexpectedValueException::class);

    Schema::dropIfExists('crud_single_rel_parent');
});
