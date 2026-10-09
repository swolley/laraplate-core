<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\ModifyRequestData;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\CrudService;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\Core\Support\PermissionName;
use Modules\Core\Tests\Fixtures\CrudSyncRelChild;
use Modules\Core\Tests\Fixtures\CrudSyncRelParent;
use Modules\Core\Tests\Stubs\Crud\SyncPartOwnerStub;
use Modules\Core\Tests\Stubs\Crud\SyncPartStub;

/*
 * Syncing a relation in a write works only within the related records the writer can read (spec 8.2): hidden
 * related records are never detached, and attaching a record the writer cannot read is refused, as a whole.
 */

/**
 * Logs in a writer who may update the parent and select the children, narrowed to the given ids when a list is
 * given. With `$may_select_children` false the writer may not read the related entity at all.
 *
 * @param  list<int>|null  $visible_child_ids
 */
function scoped_sync_writer(?array $visible_child_ids, bool $may_select_children = true): User
{
    $role = Role::factory()->create(['name' => 'scoped_sync_' . uniqid(), 'guard_name' => 'web']);
    $update = Permission::query()->firstOrCreate(['name' => PermissionName::forClass(CrudSyncRelParent::class, 'update'), 'guard_name' => 'web']);
    $select = Permission::query()->firstOrCreate(['name' => PermissionName::forClass(CrudSyncRelChild::class, 'select'), 'guard_name' => 'web']);
    $role->givePermissionTo($update);

    if ($may_select_children) {
        $role->givePermissionTo($select);
    }

    if ($may_select_children && $visible_child_ids !== null) {
        $acl = new ACL;
        $acl->setSkipValidation(true);
        $acl->forceFill([
            'permission_id' => $select->id,
            'role_id' => $role->id,
            'filters' => new FiltersGroup([new Filter('id', $visible_child_ids, FilterOperator::In)], WhereClause::And),
            'unrestricted' => false,
            'priority' => 10,
            'is_active' => true,
        ]);
        $acl->save();
    }

    $user = User::factory()->create(['username' => 'scoped_sync_' . uniqid(), 'email' => 'scoped_sync_' . uniqid() . '@example.com']);
    $user->assignRole($role);
    auth()->login($user->fresh());

    return $user->fresh();
}

/**
 * @param  array<string, mixed>  $changes
 * @param  array<string, list<int>>  $relations
 */
function scoped_sync_data(object $model, User $user, array $changes, array $relations): ModifyRequestData
{
    $request = Request::create('/modify', 'PATCH', ['id' => $model->getKey()]);
    $request->request->set('id', $model->getKey());
    $request->setUserResolver(static fn (): User => $user);

    $data = new ReflectionClass(ModifyRequestData::class)->newInstanceWithoutConstructor();

    foreach ([
        'request' => $request,
        'mainEntity' => $model->getTable(),
        'primaryKey' => 'id',
        'model' => $model,
        'connection' => $model->getConnectionName(),
        'changes' => $changes,
        'relations' => $relations,
    ] as $property => $value) {
        $reflection = new ReflectionProperty(ModifyRequestData::class, $property);
        $reflection->setValue($data, $value);
    }

    return $data;
}

/**
 * @return list<int>
 */
function scoped_sync_attached(CrudSyncRelParent $parent): array
{
    return $parent->children()->pluck('crud_sync_rel_child.id')->map(static fn (mixed $id): int => (int) $id)->sort()->values()->all();
}

beforeEach(function (): void {
    Cache::flush();
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
    $this->parent = CrudSyncRelParent::query()->create(['name' => 'root']);
    $this->children = collect(range(1, 4))->map(fn (): CrudSyncRelChild => CrudSyncRelChild::query()->create());
    [$this->one, $this->two, $this->three, $this->four] = $this->children->pluck('id')->all();
});

afterEach(function (): void {
    Schema::dropIfExists('crud_sync_rel_pivot');
    Schema::dropIfExists('crud_sync_rel_child');
    Schema::dropIfExists('crud_sync_rel_parent');
});

it('detaches only what the writer can read and keeps the hidden records attached', function (): void {
    $this->parent->children()->sync([$this->one, $this->two, $this->three]);
    $writer = scoped_sync_writer([$this->one, $this->two, $this->four]);

    $this->service->update(scoped_sync_data($this->parent, $writer, [], ['children' => [$this->one]]));

    expect(scoped_sync_attached($this->parent))->toBe([$this->one, $this->three]);
});

it('attaches a readable record next to the hidden ones', function (): void {
    $this->parent->children()->sync([$this->one, $this->three]);
    $writer = scoped_sync_writer([$this->one, $this->two, $this->four]);

    $this->service->update(scoped_sync_data($this->parent, $writer, [], ['children' => [$this->one, $this->two]]));

    expect(scoped_sync_attached($this->parent))->toBe([$this->one, $this->two, $this->three]);
});

it('clears only the readable records on an empty list', function (): void {
    $this->parent->children()->sync([$this->one, $this->two, $this->three]);
    $writer = scoped_sync_writer([$this->one, $this->two]);

    $this->service->update(scoped_sync_data($this->parent, $writer, [], ['children' => []]));

    expect(scoped_sync_attached($this->parent))->toBe([$this->three]);
});

it('refuses to attach a record the writer cannot read and writes nothing at all', function (): void {
    $this->parent->children()->sync([$this->one, $this->two]);
    $writer = scoped_sync_writer([$this->one, $this->two, $this->four]);

    expect(fn () => $this->service->update(scoped_sync_data($this->parent, $writer, ['name' => 'renamed'], ['children' => [$this->one, $this->two, $this->three]])))
        ->toThrow(AuthorizationException::class);

    expect(scoped_sync_attached($this->parent))->toBe([$this->one, $this->two])
        ->and($this->parent->fresh()->name)->toBe('root');
});

it('refuses a hidden record that is already attached when the writer submits it', function (): void {
    $this->parent->children()->sync([$this->one, $this->three]);
    $writer = scoped_sync_writer([$this->one, $this->two]);

    expect(fn () => $this->service->update(scoped_sync_data($this->parent, $writer, [], ['children' => [$this->one, $this->three]])))
        ->toThrow(AuthorizationException::class);

    expect(scoped_sync_attached($this->parent))->toBe([$this->one, $this->three]);
});

it('refuses an id that does not exist the same way as a hidden one', function (): void {
    $writer = scoped_sync_writer([$this->one]);

    expect(fn () => $this->service->update(scoped_sync_data($this->parent, $writer, [], ['children' => [$this->one, 9999]])))
        ->toThrow(AuthorizationException::class);

    expect(scoped_sync_attached($this->parent))->toBe([]);
});

it('does not sync a relation whose entity the writer cannot read at all', function (): void {
    $this->parent->children()->sync([$this->one]);
    $writer = scoped_sync_writer(null, may_select_children: false);

    expect(fn () => $this->service->update(scoped_sync_data($this->parent, $writer, ['name' => 'renamed'], ['children' => []])))
        ->toThrow(AuthorizationException::class);

    expect(scoped_sync_attached($this->parent))->toBe([$this->one])
        ->and($this->parent->fresh()->name)->toBe('root');
});

it('syncs wholesale when no ACL narrows what the writer can read', function (): void {
    $this->parent->children()->sync([$this->one, $this->three]);
    $writer = scoped_sync_writer(null);

    $this->service->update(scoped_sync_data($this->parent, $writer, [], ['children' => [$this->two]]));

    expect(scoped_sync_attached($this->parent))->toBe([$this->two]);
});

it('still refuses a relation that is not many-to-many for a restricted writer', function (): void {
    $writer = scoped_sync_writer([$this->one]);

    expect(fn () => $this->service->update(scoped_sync_data($this->parent, $writer, [], ['offspring' => [$this->one]])))
        ->toThrow(UnexpectedValueException::class);
});

describe('parts of a parent', function (): void {
    beforeEach(function (): void {
        Schema::create('crud_sync_part_owner', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('crud_sync_part', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('owner_id')->nullable();
        });
        Schema::create('crud_sync_part_pivot', function (Blueprint $table): void {
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('part_id');
            $table->primary(['owner_id', 'part_id']);
        });
    });

    afterEach(function (): void {
        Schema::dropIfExists('crud_sync_part_pivot');
        Schema::dropIfExists('crud_sync_part');
        Schema::dropIfExists('crud_sync_part_owner');
    });

    it('sync as they always did, without a permission of their own', function (): void {
        $owner = SyncPartOwnerStub::query()->create();
        $parts = collect(range(1, 3))->map(fn (): SyncPartStub => SyncPartStub::query()->create(['owner_id' => $owner->id]));
        $owner->parts()->sync([$parts[0]->id, $parts[1]->id]);

        $role = Role::factory()->create(['name' => 'scoped_sync_' . uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::query()->firstOrCreate(['name' => PermissionName::forClass(SyncPartOwnerStub::class, 'update'), 'guard_name' => 'web']));
        $writer = User::factory()->create(['username' => 'scoped_sync_' . uniqid(), 'email' => 'scoped_sync_' . uniqid() . '@example.com']);
        $writer->assignRole($role);
        auth()->login($writer->fresh());

        $this->service->update(scoped_sync_data($owner, $writer->fresh(), [], ['parts' => [$parts[1]->id, $parts[2]->id]]));

        expect($owner->parts()->pluck('crud_sync_part.id')->map(static fn (mixed $id): int => (int) $id)->sort()->values()->all())
            ->toBe([$parts[1]->id, $parts[2]->id]);
    });
});
