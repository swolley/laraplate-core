<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\User;
use Modules\Core\Models\Version;
use Modules\Core\Services\DynamicEntityService;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\FakeVersionedModel;
use Overtrue\LaravelVersionable\VersionStrategy;

uses(LaravelTestCase::class);

it('revertWithoutSaving returns null when versionable cannot be resolved', function (): void {
    $version = new Version();
    $version->setRelation('versionable', null);

    expect($version->revertWithoutSaving())->toBeNull();
});

it('nextVersion returns null when versionable cannot be resolved', function (): void {
    $version = new Version();
    $version->setRelation('versionable', null);

    expect($version->nextVersion())->toBeNull();
});

it('getCompleteVersionable applies connection and table references', function (): void {
    $version = new Version();
    $model = new FakeVersionedModel();
    $version->setRelation('versionable', $model);
    $version->versionable_type = FakeVersionedModel::class;
    $version->connection_ref = 'sqlite';
    $version->table_ref = 'custom_table';

    $ref = new ReflectionMethod(Version::class, 'getCompleteVersionable');
    $ref->setAccessible(true);
    $resolved = $ref->invoke($version);

    expect($resolved)->toBeInstanceOf(FakeVersionedModel::class)
        ->and($resolved->getConnectionName())->toBe('sqlite')
        ->and($resolved->getTable())->toBe('custom_table');
});

it('casts contains expected json and datetime mappings', function (): void {
    $casts = (new ReflectionMethod(Version::class, 'casts'))->invoke(new Version());

    expect($casts)->toMatchArray([
        'contents' => 'json',
        'original_contents' => 'json',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'datetime',
    ]);
});

it('createForModel stores connection and table refs for dynamic entities', function (): void {
    config()->set('crud.dynamic_entities', true);
    DynamicEntityService::reset();

    $table_name = 'tmp_version_dyn_' . bin2hex(random_bytes(4));

    Schema::create($table_name, function (Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->string('name')->nullable();
        $blueprint->softDeletes();
    });

    try {
        $entity = DynamicEntityService::getInstance()->resolve($table_name);
        $inserted_id = DB::table($table_name)->insertGetId(['name' => 'row']);

        $row = $entity->newQuery()->findOrFail($inserted_id);

        $version = Version::createForModel($row);

        expect($version->connection_ref)->not->toBeNull()
            ->and($version->table_ref)->toBe($table_name);
    } finally {
        DynamicEntityService::getInstance()->clearCache($table_name);
        Schema::dropIfExists($table_name);
        DynamicEntityService::reset();
    }
});

it('revertWithoutSaving applies diff strategy using previous versions and current contents', function (): void {
    $user = User::factory()->create(['name' => 'Live']);

    $strategy = new ReflectionProperty($user, 'versionStrategy');
    $strategy->setAccessible(true);
    $strategy->setValue($user, VersionStrategy::DIFF);

    $v1 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'contents' => ['name' => 'FromPrev'],
    ]);
    $v1->created_at = now()->subMinutes(5);
    $v1->save();

    $v2 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'contents' => ['name' => 'Head'],
    ]);
    $v2->created_at = now()->subMinute();
    $v2->save();

    $v2->setRelation('versionable', $user->fresh());

    $reverted = $v2->revertWithoutSaving();

    expect($reverted)->toBeInstanceOf(User::class)
        ->and($reverted->getAttribute('name'))->toBe('Head');
});

it('revertWithoutSaving applies snapshot strategy using first version and current contents', function (): void {
    $user = User::factory()->create(['name' => 'Live']);

    $v1 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'contents' => ['name' => 'SnapshotInit'],
    ]);
    $v1->created_at = now()->subMinutes(2);
    $v1->save();

    $v2 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'contents' => ['name' => 'SnapshotTip'],
    ]);
    $v2->created_at = now()->subMinute();
    $v2->save();

    $versionable = $user->fresh();
    $strategy = new ReflectionProperty($versionable, 'versionStrategy');
    $strategy->setAccessible(true);
    $strategy->setValue($versionable, VersionStrategy::SNAPSHOT);

    $v2->setRelation('versionable', $versionable);

    $reverted = $v2->revertWithoutSaving();

    expect($reverted)->toBeInstanceOf(User::class)
        ->and($reverted->getAttribute('name'))->toBe('SnapshotTip');
});

it('revertWithoutSaving snapshot skips merge when first version has empty contents', function (): void {
    $user = User::factory()->create(['name' => 'Live']);

    $v1 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'contents' => [],
    ]);
    $v1->created_at = now()->subMinutes(2);
    $v1->save();

    $v2 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'contents' => ['name' => 'OnlyTip'],
    ]);
    $v2->created_at = now()->subMinute();
    $v2->save();

    $versionable = $user->fresh();
    $strategy = new ReflectionProperty($versionable, 'versionStrategy');
    $strategy->setAccessible(true);
    $strategy->setValue($versionable, VersionStrategy::SNAPSHOT);

    $v2->setRelation('versionable', $versionable);

    $reverted = $v2->revertWithoutSaving();

    expect($reverted)->toBeInstanceOf(User::class)
        ->and($reverted->getAttribute('name'))->toBe('OnlyTip');
});

it('previousVersions returns earlier versions for the same versionable', function (): void {
    $user = User::factory()->create();

    $v1 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'contents' => [],
    ]);
    $v1->created_at = now()->subMinutes(3);
    $v1->save();

    $v2 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'contents' => [],
    ]);
    $v2->created_at = now()->subMinutes(2);
    $v2->save();

    $v2->setRelation('versionable', $user->fresh());

    $ids = $v2->previousVersions()->orderOldestFirst()->pluck('id')->all();

    expect($ids)->toContain($v1->getKey());
});

it('nextVersion returns the following version when one exists', function (): void {
    $user = User::factory()->create();

    $v1 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'contents' => [],
    ]);
    $v1->created_at = now()->subMinutes(2);
    $v1->save();

    $v2 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'contents' => [],
    ]);
    $v2->created_at = now()->subMinute();
    $v2->save();

    $v1->setRelation('versionable', $user->fresh());

    $next = $v1->nextVersion();

    expect($next)->not->toBeNull()
        ->and($next->getKey())->toBe($v2->getKey());
});

it('toArray masks bcrypt like strings inside versionable_data', function (): void {
    $bcrypt_like = '$2y$10$' . str_repeat('a', 53);

    $version = new Version();
    $version->setRawAttributes([
        'id' => 1,
        'versionable_data' => ['hash' => $bcrypt_like],
    ]);

    $serialized = $version->toArray();

    expect($serialized['versionable_data']['hash'])->toBe('[hidden]');
});
