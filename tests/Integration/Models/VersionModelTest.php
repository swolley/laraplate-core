<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Enums\VersionChangeType;
use Modules\Core\Enums\VersionSetKind;
use Modules\Core\Models\User;
use Modules\Core\Models\Version;
use Modules\Core\Models\VersionSet;
use Modules\Core\Services\DynamicEntityService;
use Modules\Core\Tests\Stubs\FakeVersionedModel;
use Overtrue\LaravelVersionable\VersionStrategy;

function persistVersionModelFixture(Version $version): void
{
    $version_set = VersionSet::query()->create([
        'uuid' => (string) Str::uuid(),
        'root_type' => $version->versionable_type,
        'root_id' => (string) $version->versionable_id,
        'kind' => VersionSetKind::Change,
    ]);

    $version->forceFill([
        'version_set_id' => $version_set->getKey(),
        'sequence' => 1,
        'change_type' => VersionChangeType::Updated,
    ])->saveOrFail();
}

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
    config()->set('core.crud.dynamic_entities', true);
    DynamicEntityService::reset();

    $table_name = 'tmp_version_dyn_' . bin2hex(random_bytes(4));
    $connection = DB::connection((string) config('database.default'));
    $schema = $connection->getSchemaBuilder();

    $schema->create($table_name, function (Blueprint $blueprint): void {
        $blueprint->id();
        $blueprint->string('name')->nullable();
        $blueprint->boolean('is_deleted')->default(false);
        $blueprint->softDeletes();
    });

    try {
        $entity = DynamicEntityService::getInstance()->resolve($table_name);
        $inserted_id = $connection->table($table_name)->insertGetId(['name' => 'row']);

        $row = $entity->newQuery()->findOrFail($inserted_id);

        $version = Version::createForModel($row);

        expect($version->connection_ref)->not->toBeNull()
            ->and($version->table_ref)->toBe($table_name);
    } finally {
        DynamicEntityService::getInstance()->clearCache($table_name);
        $schema->dropIfExists($table_name);
        DynamicEntityService::reset();
    }
});

it('revertWithoutSaving applies diff strategy using previous versions and current contents', function (): void {
    $base_user = User::factory()->create(['name' => 'Live']);
    $user = new class extends User
    {
        public VersionStrategy $versionStrategy = VersionStrategy::DIFF;

        protected $table = 'users';
    };
    $user->setConnection(config('database.default'));
    $user->exists = true;
    $user->setRawAttributes(array_merge($base_user->getAttributes(), ['deleted_at' => null]), true);

    $v1 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'version_strategy' => VersionStrategy::DIFF,
        'contents' => ['name' => 'FromPrev'],
    ]);
    $v1->created_at = now()->subMinutes(5);
    persistVersionModelFixture($v1);

    $v2 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'version_strategy' => VersionStrategy::DIFF,
        'contents' => ['name' => 'Head'],
    ]);
    $v2->created_at = now()->subMinute();
    persistVersionModelFixture($v2);

    $v2->setRelation('versionable', $user->fresh());

    $reverted = $v2->revertWithoutSaving();

    expect($reverted)->toBeInstanceOf(User::class)
        ->and($reverted->getAttribute('name'))->toBe('Head');
});

it('revertWithoutSaving applies snapshot strategy using first version and current contents', function (): void {
    $base_user = User::factory()->create(['name' => 'Live']);
    $user = new class extends User
    {
        public VersionStrategy $versionStrategy = VersionStrategy::SNAPSHOT;

        protected $table = 'users';
    };
    $user->setConnection(config('database.default'));
    $user->exists = true;
    $user->setRawAttributes(array_merge($base_user->getAttributes(), ['deleted_at' => null]), true);

    $v1 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'version_strategy' => VersionStrategy::SNAPSHOT,
        'contents' => ['name' => 'SnapshotInit'],
    ]);
    $v1->created_at = now()->subMinutes(2);
    persistVersionModelFixture($v1);

    $v2 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'version_strategy' => VersionStrategy::SNAPSHOT,
        'contents' => ['name' => 'SnapshotTip'],
    ]);
    $v2->created_at = now()->subMinute();
    persistVersionModelFixture($v2);

    $versionable = $user->fresh();

    $v2->setRelation('versionable', $versionable);

    $reverted = $v2->revertWithoutSaving();

    expect($reverted)->toBeInstanceOf(User::class)
        ->and($reverted->getAttribute('name'))->toBe('SnapshotTip');
});

it('revertWithoutSaving snapshot skips merge when first version has empty contents', function (): void {
    $base_user = User::factory()->create(['name' => 'Live']);
    $user = new class extends User
    {
        public VersionStrategy $versionStrategy = VersionStrategy::SNAPSHOT;

        protected $table = 'users';
    };
    $user->setConnection(config('database.default'));
    $user->exists = true;
    $user->setRawAttributes(array_merge($base_user->getAttributes(), ['deleted_at' => null]), true);

    $v1 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'version_strategy' => VersionStrategy::SNAPSHOT,
        'contents' => [],
    ]);
    $v1->created_at = now()->subMinutes(2);
    persistVersionModelFixture($v1);

    $v2 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'version_strategy' => VersionStrategy::SNAPSHOT,
        'contents' => ['name' => 'OnlyTip'],
    ]);
    $v2->created_at = now()->subMinute();
    persistVersionModelFixture($v2);

    $versionable = $user->fresh();

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
        'version_strategy' => VersionStrategy::DIFF,
        'contents' => [],
    ]);
    $v1->created_at = now()->subMinutes(3);
    persistVersionModelFixture($v1);

    $v2 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'version_strategy' => VersionStrategy::DIFF,
        'contents' => [],
    ]);
    $v2->created_at = now()->subMinutes(2);
    persistVersionModelFixture($v2);

    $v2->setRelation('versionable', $user->fresh());

    $ids = $v2->previousVersions()->orderOldestFirst()->pluck('id')->all();

    expect($ids)->toContain($v1->getKey());
});

it('nextVersion returns the following version when one exists', function (): void {
    $user = User::factory()->create();

    $v1 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'version_strategy' => VersionStrategy::DIFF,
        'contents' => [],
    ]);
    $v1->created_at = now()->subMinutes(2);
    persistVersionModelFixture($v1);

    $v2 = new Version([
        'versionable_id' => $user->getKey(),
        'versionable_type' => $user->getMorphClass(),
        'version_strategy' => VersionStrategy::DIFF,
        'contents' => [],
    ]);
    $v2->created_at = now()->subMinute();
    persistVersionModelFixture($v2);

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

it('toArray masks in the contents every attribute the versioned model hides, whatever its hash algorithm', function (): void {
    $user = new User();
    $version = new Version();
    $version->setRawAttributes([
        'id' => 1,
        'versionable_type' => $user->getMorphClass(),
        'versionable_id' => 1,
        'contents' => json_encode([
            'name' => 'versioned-name',
            'password' => '$argon2id$v=19$m=65536,t=4,p=1$versioned-salt$versioned-hash',
            'remember_token' => 'versioned-remember-token',
            'two_factor_secret' => 'versioned-2fa-secret',
            'two_factor_recovery_codes' => 'versioned-recovery-codes',
        ]),
        'original_contents' => json_encode([
            'password' => '$argon2id$v=19$m=65536,t=4,p=1$original-salt$original-hash',
            'remember_token' => 'original-remember-token',
        ]),
    ]);

    $serialized = $version->toArray();
    $json = json_encode($serialized, JSON_THROW_ON_ERROR);

    expect($serialized['contents']['name'])->toBe('versioned-name')
        ->and($serialized['contents']['password'])->toBe('[hidden]')
        ->and($serialized['original_contents']['remember_token'])->toBe('[hidden]')
        ->and($json)->not->toContain('versioned-hash')
        ->and($json)->not->toContain('original-hash')
        ->and($json)->not->toContain('versioned-remember-token')
        ->and($json)->not->toContain('versioned-2fa-secret')
        ->and($json)->not->toContain('versioned-recovery-codes');
});

it('toArray masks in the contents the attributes the versioned model hides from the anonymous user, for the anonymous user only', function (): void {
    $stub = new Modules\Core\Tests\Stubs\Models\HidesAttributesStub();
    $version = new Version();
    $version->setRawAttributes([
        'id' => 1,
        'versionable_type' => $stub->getMorphClass(),
        'versionable_id' => 1,
        'contents' => json_encode(['title' => 'versioned-title', 'user_id' => 4242]),
    ]);

    Illuminate\Support\Facades\Cache::flush();
    $anonymous = User::factory()->create(['name' => config('permission.users.guest'), 'username' => config('permission.users.guest')]);
    Illuminate\Support\Facades\Auth::setUser($anonymous);

    expect($version->toArray()['contents'])->toBe(['title' => 'versioned-title', 'user_id' => '[hidden]']);

    Illuminate\Support\Facades\Auth::setUser(User::factory()->create());

    expect($version->toArray()['contents'])->toBe(['title' => 'versioned-title', 'user_id' => 4242]);
});
