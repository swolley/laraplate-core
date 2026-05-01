<?php

declare(strict_types=1);

use Modules\Core\Actions\Grids\GetGridConfigsAction;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;


it('returns configs for models', function (): void {
    $auth = $this->app->make(AuthorizationService::class);
    $action = new GetGridConfigsAction(
        $auth,
        modelsProvider: fn () => ['ModelOne'],
        gridResolver: fn () => ['config' => true],
    );

    $result = $action(request(), null);

    expect($result)->toBe(['ModelOne' => ['config' => true]]);
});

it('filters single entity', function (): void {
    $auth = $this->app->make(AuthorizationService::class);
    $action = new GetGridConfigsAction(
        $auth,
        modelsProvider: fn () => ['ModelOne'],
        gridResolver: fn ($model, $entity) => $entity === 'ModelOne' ? ['only' => true] : null,
    );

    $result = $action(request(), 'ModelOne');

    expect($result)->toBe(['only' => true]);
});

it('throws when single entity is requested and no grid is found', function (): void {
    $auth = $this->app->make(AuthorizationService::class);
    $action = new GetGridConfigsAction(
        $auth,
        modelsProvider: fn () => ['ModelOne'],
        gridResolver: fn () => null,
    );

    expect(fn () => $action(request(), 'MissingModel'))
        ->toThrow(UnexpectedValueException::class, "'MissingModel' is not a Grid");
});

it('skips null grid resolver responses and keeps valid ones', function (): void {
    $auth = $this->app->make(AuthorizationService::class);
    $action = new GetGridConfigsAction(
        $auth,
        modelsProvider: fn () => ['One', 'Two'],
        gridResolver: fn (string $model) => $model === 'Two' ? ['ok' => true] : null,
    );

    $result = $action(request(), null);

    expect($result)->toBe(['Two' => ['ok' => true]]);
});

it('uses metadata registry path and indexes results by table name', function (): void {
    Modules\Core\Inspector\ModelMetadataRegistry::reset();
    config()->set('auth.providers.users.model', Modules\Core\Models\User::class);
    config()->set('permission.roles.superadmin', 'superadmin');
    $auth = $this->app->make(AuthorizationService::class);

    $model_class = new class extends Illuminate\Database\Eloquent\Model
    {
        use HasGridUtils;

        protected $table = 'fake_grid_table';

        public function getGrid(): object
        {
            return new class
            {
                public function getConfigs(): array
                {
                    return ['table' => true];
                }
            };
        }
    };
    $class_name = $model_class::class;
    $super_role = Role::findOrCreate('superadmin', 'web');
    $user = User::factory()->create();
    $user->assignRole($super_role);
    $request = request();
    $request->setUserResolver(fn (): User => $user);

    $action = new GetGridConfigsAction(
        $auth,
        modelsProvider: fn () => [$class_name],
    );

    $result = $action($request, null);

    expect($result)->toBe(['fake_grid_table' => ['table' => true]]);
});

it('returns null in private getModelGridConfigs when permission check fails', function (): void {
    $auth = $this->app->make(AuthorizationService::class);

    $instance = new class extends Illuminate\Database\Eloquent\Model
    {
        use HasGridUtils;

        protected $table = 'private_grid_table';
    };

    $action = new GetGridConfigsAction($auth);
    $method = new ReflectionMethod(GetGridConfigsAction::class, 'getModelGridConfigs');
    $method->setAccessible(true);
    $request = request();
    $request->setUserResolver(fn (): Illuminate\Contracts\Auth\Authenticatable => Mockery::mock(Illuminate\Contracts\Auth\Authenticatable::class));
    $result = $method->invoke($action, '', $instance, 'private_grid_table', $request);

    expect($result)->toBeNull();
});

it('skips models without grid utils in metadata registry path', function (): void {
    Modules\Core\Inspector\ModelMetadataRegistry::reset();
    $auth = $this->app->make(AuthorizationService::class);

    $plain_model = new class extends Illuminate\Database\Eloquent\Model
    {
        protected $table = 'plain_grid_table';
    };

    $action = new GetGridConfigsAction(
        $auth,
        modelsProvider: fn () => [$plain_model::class],
    );

    expect($action(request(), null))->toBe([]);
});
