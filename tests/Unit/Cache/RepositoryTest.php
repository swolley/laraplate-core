<?php

declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Cache\HasCache;
use Modules\Core\Cache\Repository;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;


it('getCacheTags prepends app name and supports string/array tags', function (): void {
    $store = Mockery::mock(Store::class);
    $repository = new Repository($store);

    $from_string = $repository->getCacheTags('users');
    $from_array = $repository->getCacheTags(['users', 'roles']);

    expect($from_string[0])->toBe(config('app.name'))
        ->and($from_string)->toContain('users')
        ->and($from_array)->toContain('users')
        ->and($from_array)->toContain('roles');
});

it('tryByRequest returns callback immediately when model does not use cache', function (): void {
    $repository = new Repository(new ArrayStore());

    $request = Request::create('/api/items', 'GET');
    $request->setUserResolver(fn (): User => User::factory()->create());

    $result = $repository->tryByRequest(
        new class extends Model
        {
            protected $table = 'plain_models';
        },
        $request,
        fn () => ['fresh' => true],
    );

    expect($result)->toBe(['fresh' => true]);
});

it('tryByRequest unwraps ResponseBuilder response when remember callback runs', function (): void {
    $repository = new Repository(new ArrayStore());

    $request = Request::create('/api/items', 'GET', ['z' => '2', 'a' => '1']);
    $request->setUserResolver(fn (): User => User::factory()->create());

    $model = new class extends Model
    {
        use HasCache;

        protected $table = 'cacheable_models';
    };

    $result = $repository->tryByRequest(
        $model,
        $request,
        fn () => (new ResponseBuilder($request))->setData(['wrapped' => true]),
        30,
    );

    expect($result)->toBeInstanceOf(JsonResponse::class);
});

it('clear methods flush and forget through tags for entity, request, user and group', function (): void {
    $repository = new Repository(new ArrayStore());
    config()->set('auth.providers.users.model', Modules\Core\Models\User::class);

    $cacheable = new class extends Model
    {
        use HasCache;

        protected $table = 'cacheable_entities';
    };

    $request = Request::create('/api/cache', 'GET', ['a' => '1']);
    $request->setUserResolver(fn (): User => User::factory()->create());

    $repository->clearByEntity($cacheable);
    $repository->clearByRequest($request, $cacheable);
    $repository->clearByRequest($request, null);

    $user = User::factory()->create();
    $role = Role::factory()->create(['guard_name' => 'web']);
    $user->assignRole($role);
    $repository->clearByUser($user, $cacheable);
    $repository->clearByUser($user, null);
    $repository->clearByGroup($role, $cacheable);
    $repository->clearByGroup($role, null);

    expect(true)->toBeTrue();
});

it('private helpers compute request key and duration variants', function (): void {
    config()->set('cache.duration', 120);
    config()->set('cache.threshold', 10);

    $repository = new Repository(new ArrayStore());
    config()->set('auth.providers.users.model', Modules\Core\Models\User::class);

    $request = Request::create('/api/key', 'GET', ['b' => '2', 'a' => ['z' => '3', 'c' => '1']]);
    $user = User::factory()->create();
    $role = Role::factory()->create(['guard_name' => 'web']);
    $user->assignRole($role);
    $request->setUserResolver(fn (): User => $user);

    $key_method = new ReflectionMethod(Repository::class, 'getKeyFromRequest');
    $key_method->setAccessible(true);
    $key = $key_method->invoke($repository, $request);

    $duration_method = new ReflectionMethod(Repository::class, 'getDuration');
    $duration_method->setAccessible(true);
    $duration = $duration_method->invoke($repository);

    config()->set('cache.threshold', 0);
    $duration_without_threshold = $duration_method->invoke($repository);

    expect($key)->toBeString()
        ->and($duration)->toBeArray()
        ->and($duration_without_threshold)->toBe(120);
});

it('remember supports flexible array ttl branch', function (): void {
    $repository = new Repository(new ArrayStore());

    $result = $repository->remember('repo-flexible-key', [1, 5], fn () => 'flexible');

    expect($result)->toBe('flexible');
});

it('tryByRequest supports entity arrays with class strings and plain return values', function (): void {
    $repository = new Repository(new ArrayStore());

    $request = Request::create('/api/multi', 'GET', ['x' => '1']);
    $request->setUserResolver(fn (): User => User::factory()->create());

    $model = new class extends Model
    {
        use HasCache;

        protected $table = 'multi_cache_models';
    };

    $result = $repository->tryByRequest(
        [$model::class, $model],
        $request,
        fn () => ['raw' => true],
        10,
    );

    expect($result)->toBe(['raw' => true]);
});

it('clear methods support class-string entities and role/user key helpers branches', function (): void {
    $repository = new Repository(new ArrayStore());
    config()->set('auth.providers.users.model', Modules\Core\Models\User::class);
    $request = Request::create('/api/clear-string', 'GET', ['a' => '1']);
    $request->setUserResolver(fn (): User => User::factory()->create());

    $cacheable = new class extends Model
    {
        use HasCache;

        protected $table = 'string_cacheable_entities';
    };
    $class = $cacheable::class;

    $repository->clearByEntity($class);
    $repository->clearByRequest($request, $class);

    $role = Role::factory()->create(['guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);
    $repository->clearByUser($user, $class);
    $repository->clearByGroup($role, $class);

    $method = new ReflectionMethod(Repository::class, 'getKeyPartsFromUser');
    $method->setAccessible(true);
    $parts_from_roles = $method->invoke($repository, $user);
    expect($parts_from_roles)->toContain('U' . $user->id);

    $groups_user = new class extends User
    {
        public function groups()
        {
            return null;
        }
    };
    $groups_user->id = 776;
    $group_c = new class extends Model {};
    $group_c->id = 12;
    $groups_user->setRelation('groups', collect([$group_c]));
    $parts_from_groups = $method->invoke($repository, $groups_user);
    expect($parts_from_groups)->toContain('R12')->toContain('U776');

    $user_groups_user = new class extends User
    {
        public function user_groups()
        {
            return null;
        }
    };
    $user_groups_user->id = 777;
    $group_a = new class extends Model {};
    $group_a->id = 10;
    $group_b = new class extends Model {};
    $group_b->id = 5;
    $user_groups_user->setRelation('user_groups', collect([$group_a, $group_b]));
    $parts_from_user_groups = $method->invoke($repository, $user_groups_user);
    expect($parts_from_user_groups)->toContain('R5')->toContain('R10');

    $user_roles_user = new class extends User
    {
        public function user_roles()
        {
            return null;
        }
    };
    $user_roles_user->id = 778;
    $role_model = new class extends Model {};
    $role_model->id = 8;
    $user_roles_user->setRelation('user_roles', collect([$role_model]));
    $parts_from_user_roles = $method->invoke($repository, $user_roles_user);

    expect($parts_from_user_groups)->toContain('U777')
        ->and($parts_from_user_roles)->toContain('R8')
        ->and($parts_from_user_roles)->toContain('U778');
});
