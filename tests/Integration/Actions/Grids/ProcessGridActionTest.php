<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Actions\Grids\ProcessGridAction;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;


it('processes grid with resolvers', function (): void {
    config()->set('auth.providers.users.model', Modules\Core\Models\User::class);
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin', 'web'));
    Auth::login($user);

    $auth = $this->app->make(AuthorizationService::class);
    $request = new class($user) extends Request
    {
        public function __construct(
            private User $authUser,
        ) {
            parent::__construct();
        }

        public function user($guard = null)
        {
            return $this->authUser;
        }

        public function parsed(): array
        {
            return [
                'connection' => null,
                'action' => (object) ['value' => 'select'],
            ];
        }
    };

    $model = new class
    {
        public function getTable(): string
        {
            return 'table';
        }

        public function getConnectionName(): ?string
        {
            return 'connection';
        }
    };

    $action = new ProcessGridAction(
        auth: $auth,
        entityResolver: fn () => $model,
        gridFactory: fn () => new class
        {
            public function process(): JsonResponse
            {
                return response()->json(['ok' => true]);
            }
        },
    );

    $response = $action($request, 'entity');

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toBe(['ok' => true]);
});

it('extractActionValue handles object and default branches', function (): void {
    $auth = $this->app->make(AuthorizationService::class);
    $action = new ProcessGridAction($auth);
    $method = new ReflectionMethod(ProcessGridAction::class, 'extractActionValue');
    $method->setAccessible(true);

    $from_object = $method->invoke($action, (object) ['action' => (object) ['value' => 'update']]);
    $from_default = $method->invoke($action, ['something' => 'else']);

    expect($from_object)->toBe('update')
        ->and($from_default)->toBe('select');
});

it('uses DynamicEntity resolver branch when no custom resolver is provided', function (): void {
    config()->set('auth.providers.users.model', Modules\Core\Models\User::class);
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('superadmin', 'web'));
    Auth::login($user);

    $auth = $this->app->make(AuthorizationService::class);
    $request = new class($user) extends Request
    {
        public function __construct(
            private User $authUser,
        ) {
            parent::__construct();
        }

        public function user($guard = null)
        {
            return $this->authUser;
        }

        public function parsed(): array
        {
            return [
                'connection' => null,
                'action' => (object) ['value' => 'select'],
            ];
        }
    };

    $action = new ProcessGridAction(
        auth: $auth,
        entityResolver: null,
        gridFactory: fn () => new class
        {
            public function process(): JsonResponse
            {
                return response()->json(['resolved' => true]);
            }
        },
    );

    $response = $action($request, 'setting');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe(['resolved' => true]);
});
