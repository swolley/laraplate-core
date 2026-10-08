<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Middleware\AuthenticateApiRequest;
use Modules\Core\Http\Middleware\SelfAuthenticatedApiRoute;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Support\CrudApiExposure;

const API_ACCESS_ENTITY_PERMISSION = 'default.users.select';

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('api');
    config()->set('permission.roles.superadmin', 'superadmin');
    config()->set('core.api.rate_limit_per_minute', 600);
});

/**
 * Make sure the permission exists for both guards, as the seeder does.
 */
function apiAccessEnsurePermission(string $name): void
{
    foreach (['web', 'api'] as $guard) {
        Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => $guard]);
    }
}

/**
 * The instance of the model the `users` provider hands out: Sanctum only accepts the tokens of that class.
 */
function apiAccessTokenable(User $user): App\Models\User
{
    return App\Models\User::query()->findOrFail($user->getKey());
}

/**
 * A user whose `api` role holds the select permission of the users entity.
 */
function apiAccessUserWithGrant(?string $name = null): App\Models\User
{
    apiAccessEnsurePermission(API_ACCESS_ENTITY_PERMISSION);

    $role = Role::factory()->create(['name' => $name ?? 'api_reader_' . uniqid(), 'guard_name' => 'api']);
    $role->givePermissionTo(Permission::query()->where(['name' => API_ACCESS_ENTITY_PERMISSION, 'guard_name' => 'api'])->firstOrFail());

    $user = apiAccessTokenable(User::factory()->create());
    $user->assignRole($role);

    return $user->fresh();
}

/**
 * The anonymous account the installation seeds, optionally holding the select permission through an `api` role.
 */
function apiAccessAnonymous(bool $withGrant = false): User
{
    $anonymous = User::query()->where('name', 'anonymous')->first()
        ?? User::factory()->create(['name' => 'anonymous', 'username' => 'anonymous']);

    if ($withGrant) {
        apiAccessEnsurePermission(API_ACCESS_ENTITY_PERMISSION);
        $role = Role::factory()->create(['name' => 'api_guest_' . uniqid(), 'guard_name' => 'api']);
        $role->givePermissionTo(Permission::query()->where(['name' => API_ACCESS_ENTITY_PERMISSION, 'guard_name' => 'api'])->firstOrFail());
        $anonymous->assignRole($role);
    }

    return $anonymous->fresh();
}

/**
 * @return array<string, string>
 */
function apiAccessBearer(string $token): array
{
    return ['Authorization' => 'Bearer ' . $token];
}

/**
 * What CrudController answers to a permission an authenticated principal lacks.
 *
 * @return array<string, mixed>
 */
function apiAccessDenied(int $status = 403): array
{
    return ['meta' => ['status' => $status], 'error' => 'User not allowed to access this resource'];
}

function apiAccessUrl(): string
{
    return '/api/v1/select/core/users';
}

describe('the api switch', function (): void {
    it('refuses a Core CRUD route and every module route with 403 while the switch is off', function (string $method, string $uri): void {
        $this->json($method, $uri)->assertForbidden();
    })->with([
        'core crud' => ['GET', '/api/v1/select/core/users'],
        'core graph' => ['GET', '/api/v1/crud/graph/search/core/users'],
        'mes machine data' => ['POST', '/api/v1/mes/machine-data'],
        'sao webhook' => ['POST', '/api/v1/webhooks/1'],
        'cms relation' => ['GET', '/api/v1/select/cms/tags/1/contents'],
    ]);

    it('lets the switch decide before the token is looked at', function (): void {
        $user = apiAccessUserWithGrant();
        $token = $user->createToken('t', [API_ACCESS_ENTITY_PERMISSION]);

        $this->getJson(apiAccessUrl(), apiAccessBearer($token->plainTextToken))->assertForbidden();
        $this->getJson(apiAccessUrl(), apiAccessBearer('garbage'))->assertForbidden();
    });

    it('keeps the default of the switch false', function (): void {
        expect(config('core.expose_api'))->toBeFalse();
    });
});

describe('the anonymous fallback', function (): void {
    it('makes the anonymous user the request user on the api guard when there is no header', function (): void {
        CrudApiExposure::enable();
        $anonymous = apiAccessAnonymous();
        config()->set('core.expose_api', true);

        $request = Request::create(apiAccessUrl());
        $seen = null;

        $response = app(AuthenticateApiRequest::class)->handle($request, function (Request $request) use (&$seen) {
            $seen = ['user' => $request->user()?->getKey(), 'driver' => Auth::getDefaultDriver(), 'guard' => Auth::guard('api')->user()?->getKey()];

            return response('ok');
        });

        expect($response->getStatusCode())->toBe(200)
            ->and($seen)->toBe(['user' => $anonymous->getKey(), 'driver' => 'api', 'guard' => $anonymous->getKey()]);
    });

    it('serves an anonymous request with the permissions of the api roles of the anonymous user', function (): void {
        CrudApiExposure::enable();
        apiAccessAnonymous(withGrant: true);

        $this->getJson(apiAccessUrl())->assertOk();
    });

    it('denies an anonymous request when the anonymous api roles hold no permission', function (): void {
        CrudApiExposure::enable();
        apiAccessEnsurePermission(API_ACCESS_ENTITY_PERMISSION);
        apiAccessAnonymous();

        $this->getJson(apiAccessUrl())->assertUnauthorized()->assertJson(apiAccessDenied(401));
    });

    it('refuses an unsigned anonymous webhook delivery at the route', function (): void {
        CrudApiExposure::enable();
        apiAccessAnonymous();

        $response = $this->postJson('/api/v1/webhooks/999999', ['identifier' => 'x']);

        expect($response->getStatusCode())->toBeIn([401, 403, 404, 422]);
    });
});

describe('bearer tokens', function (): void {
    it('answers 401 for a bad token and never falls back to the anonymous user', function (string $case): void {
        CrudApiExposure::enable();
        apiAccessAnonymous(withGrant: true);
        $user = apiAccessUserWithGrant();

        $header = match ($case) {
            'malformed' => 'not-a-token',
            'empty' => '',
            'unknown' => '999999|' . str_repeat('a', 40),
            'expired' => $user->createToken('t', [API_ACCESS_ENTITY_PERMISSION], now()->subMinute())->plainTextToken,
            'revoked' => (function () use ($user): string {
                $token = $user->createToken('t', [API_ACCESS_ENTITY_PERMISSION]);
                $token->accessToken->delete();

                return $token->plainTextToken;
            })(),
        };

        $this->getJson(apiAccessUrl(), apiAccessBearer($header))->assertUnauthorized();
    })->with(['malformed', 'empty', 'unknown', 'expired', 'revoked']);

    it('answers 401 for the token of a superadmin', function (): void {
        CrudApiExposure::enable();
        $user = apiAccessTokenable(User::factory()->create());
        $user->assignRole(Role::findOrCreate('superadmin', 'web'));
        $token = $user->createToken('t', ['*']);

        $this->getJson(apiAccessUrl(), apiAccessBearer($token->plainTextToken))->assertUnauthorized();
    });

    it('answers 403 and logs without the token when the client address is outside the allowed networks', function (): void {
        CrudApiExposure::enable();
        Log::spy();
        $user = apiAccessUserWithGrant();
        $token = $user->createToken('t', [API_ACCESS_ENTITY_PERMISSION]);
        $token->accessToken->forceFill(['allowed_cidrs' => json_encode(['10.0.0.0/8'])])->save();
        $secret = explode('|', $token->plainTextToken, 2)[1];

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.5'])
            ->getJson(apiAccessUrl(), apiAccessBearer($token->plainTextToken))
            ->assertForbidden();

        Log::shouldHaveReceived('warning')->withArgs(static function (string $message, array $context = []) use ($secret, $token): bool {
            $line = $message . json_encode($context);

            return str_contains($line, '192.168.1.5')
                && ! str_contains($line, $secret)
                && ! str_contains($line, $token->plainTextToken);
        })->once();
    });

    it('lets a token through from an address inside the allowed networks', function (): void {
        CrudApiExposure::enable();
        $user = apiAccessUserWithGrant();
        $token = $user->createToken('t', [API_ACCESS_ENTITY_PERMISSION]);
        $token->accessToken->forceFill(['allowed_cidrs' => json_encode(['10.0.0.0/8', '192.168.1.0/24'])])->save();

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.5'])
            ->getJson(apiAccessUrl(), apiAccessBearer($token->plainTextToken))
            ->assertOk();
    });
});

describe('token abilities and role permissions', function (): void {
    it('refuses a role permission without the token ability', function (): void {
        CrudApiExposure::enable();
        $user = apiAccessUserWithGrant();
        $token = $user->createToken('t', ['default.roles.select']);

        $this->getJson(apiAccessUrl(), apiAccessBearer($token->plainTextToken))->assertForbidden()->assertJson(apiAccessDenied());
    });

    it('refuses a token ability without the role permission', function (): void {
        CrudApiExposure::enable();
        apiAccessEnsurePermission(API_ACCESS_ENTITY_PERMISSION);
        $user = apiAccessTokenable(User::factory()->create());
        $token = $user->createToken('t', [API_ACCESS_ENTITY_PERMISSION]);

        $this->getJson(apiAccessUrl(), apiAccessBearer($token->plainTextToken))->assertForbidden()->assertJson(apiAccessDenied());
    });

    it('never accepts the wildcard ability in place of a permission', function (): void {
        CrudApiExposure::enable();
        $user = apiAccessUserWithGrant();
        $token = $user->createToken('t', ['*']);

        $this->getJson(apiAccessUrl(), apiAccessBearer($token->plainTextToken))->assertForbidden()->assertJson(apiAccessDenied());
    });

    it('refuses an authenticated user for a permission that does not exist on the api guard', function (): void {
        CrudApiExposure::enable();
        Permission::query()->where(['name' => API_ACCESS_ENTITY_PERMISSION, 'guard_name' => 'api'])->delete();
        Permission::query()->firstOrCreate(['name' => API_ACCESS_ENTITY_PERMISSION, 'guard_name' => 'web']);
        $user = apiAccessTokenable(User::factory()->create());
        $token = $user->createToken('t', [API_ACCESS_ENTITY_PERMISSION]);

        $this->getJson(apiAccessUrl(), apiAccessBearer($token->plainTextToken))->assertForbidden()->assertJson(apiAccessDenied());
    });

    it('refuses a session user who holds the permission only on the web guard', function (): void {
        CrudApiExposure::enable();
        apiAccessEnsurePermission(API_ACCESS_ENTITY_PERMISSION);
        $role = Role::factory()->create(['name' => 'web_only_' . uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::query()->where(['name' => API_ACCESS_ENTITY_PERMISSION, 'guard_name' => 'web'])->firstOrFail());
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user->fresh())->getJson(apiAccessUrl())->assertForbidden()->assertJson(apiAccessDenied());
    });

    it('serves a request that holds both the role permission and the token ability', function (): void {
        CrudApiExposure::enable();
        $user = apiAccessUserWithGrant();
        $token = $user->createToken('t', [API_ACCESS_ENTITY_PERMISSION]);

        $this->getJson(apiAccessUrl(), apiAccessBearer($token->plainTextToken))->assertOk();
    });

    it('refuses the next request after the permission was revoked from the role', function (): void {
        CrudApiExposure::enable();
        $user = apiAccessUserWithGrant('api_revocable');
        $token = $user->createToken('t', [API_ACCESS_ENTITY_PERMISSION]);

        $this->getJson(apiAccessUrl(), apiAccessBearer($token->plainTextToken))->assertOk();

        Role::query()->where(['name' => 'api_revocable', 'guard_name' => 'api'])->firstOrFail()
            ->revokePermissionTo(Permission::query()->where(['name' => API_ACCESS_ENTITY_PERMISSION, 'guard_name' => 'api'])->firstOrFail());

        $this->getJson(apiAccessUrl(), apiAccessBearer($token->plainTextToken))->assertForbidden()->assertJson(apiAccessDenied());
    });
});

describe('rate limit', function (): void {
    it('defaults to 600 requests a minute', function (): void {
        $config = require base_path('Modules/Core/config/config.php');

        expect($config['api']['rate_limit_per_minute'])->toBe(600);
    });

    it('answers 429 once the anonymous client address used its minute', function (): void {
        CrudApiExposure::enable();
        apiAccessAnonymous(withGrant: true);
        config()->set('core.api.rate_limit_per_minute', 3);

        foreach (range(1, 3) as $_) {
            $this->getJson(apiAccessUrl())->assertOk();
        }

        $this->getJson(apiAccessUrl())->assertStatus(429);
    });

    it('counts a token apart from the client address', function (): void {
        CrudApiExposure::enable();
        $user = apiAccessUserWithGrant();
        $token = $user->createToken('t', [API_ACCESS_ENTITY_PERMISSION]);
        config()->set('core.api.rate_limit_per_minute', 2);

        foreach (range(1, 2) as $_) {
            $this->getJson(apiAccessUrl(), apiAccessBearer($token->plainTextToken))->assertOk();
        }

        $this->getJson(apiAccessUrl(), apiAccessBearer($token->plainTextToken))->assertStatus(429);

        $other = $user->createToken('other', [API_ACCESS_ENTITY_PERMISSION]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])
            ->getJson(apiAccessUrl(), apiAccessBearer($other->plainTextToken))
            ->assertOk();
    });

    it('limits the requests that carry a bearer per client address before the token is looked up', function (): void {
        CrudApiExposure::enable();
        config()->set('core.api.rate_limit_per_minute', 3);

        $statuses = [];

        foreach (range(1, 4) as $attempt) {
            $statuses[] = $this->getJson(apiAccessUrl(), apiAccessBearer('1|invalid' . $attempt))->getStatusCode();
        }

        expect($statuses)->toBe([401, 401, 401, 429]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.2.2.2'])
            ->getJson(apiAccessUrl(), apiAccessBearer('1|invalid'))
            ->assertUnauthorized();
    });
});

describe('route registration', function (): void {
    it('puts the api access middleware on every api route, Core and modules', function (): void {
        $api_routes = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn (Illuminate\Routing\Route $route): bool => str_starts_with($route->uri(), 'api/v1/'));

        expect($api_routes)->not->toBeEmpty();

        foreach ($api_routes as $route) {
            $middleware = Route::gatherRouteMiddleware($route);

            expect(array_count_values($middleware)[AuthenticateApiRequest::class] ?? 0)->toBe(1)
                ->and(array_search(AuthenticateApiRequest::class, $middleware, true))
                ->toBeLessThan(array_search(SubstituteBindings::class, $middleware, true));
        }
    });

    it('registers each Core crud route once', function (): void {
        $names = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn (Illuminate\Routing\Route $route): bool => str_starts_with((string) $route->getName(), 'core.api.'))
            ->map(static fn (Illuminate\Routing\Route $route): string => (string) $route->getName())
            ->all();

        expect($names)->not->toBeEmpty()
            ->and(array_unique($names))->toHaveCount(count($names))
            ->and(file_get_contents(base_path('Modules/Core/routes/api.php')))->not->toContain('crud.php');
    });
});

describe('bearer and session', function (): void {
    it('answers 401 for a session user who sends an invalid bearer instead of falling back to the session', function (): void {
        CrudApiExposure::enable();
        $user = apiAccessUserWithGrant();

        $this->actingAs($user)->getJson(apiAccessUrl())->assertOk();

        $this->actingAs($user)->getJson(apiAccessUrl(), apiAccessBearer('1|invalid'))->assertUnauthorized();
    });

    it('takes the principal from the token alone when both a session and a token are present', function (): void {
        CrudApiExposure::enable();
        $session_user = apiAccessUserWithGrant();
        apiAccessEnsurePermission(API_ACCESS_ENTITY_PERMISSION);
        $token_user = apiAccessTokenable(User::factory()->create());
        $token = $token_user->createToken('t', [API_ACCESS_ENTITY_PERMISSION]);

        $this->actingAs($session_user)
            ->getJson(apiAccessUrl(), apiAccessBearer($token->plainTextToken))
            ->assertForbidden();
    });
});

describe('self-authenticated routes', function (): void {
    beforeEach(function (): void {
        Route::middleware('api')->post('api/v1/_probe/self', static fn (Request $request) => response()->json([
            'user' => $request->user()?->name,
            'bearer' => $request->bearerToken(),
        ]))->middleware(SelfAuthenticatedApiRoute::class);
        Route::middleware('api')->post('api/v1/_probe/plain', static fn (Request $request) => response()->json([
            'user' => $request->user()?->name,
        ]));
    });

    it('still answers 403 while the switch is off', function (): void {
        $this->postJson('/api/v1/_probe/self', [], apiAccessBearer('own-secret'))->assertForbidden();
    });

    it('hands an arbitrary bearer to the route and runs it as the anonymous user', function (): void {
        CrudApiExposure::enable();
        apiAccessAnonymous();

        $this->postJson('/api/v1/_probe/self', [], apiAccessBearer('own-secret'))
            ->assertOk()
            ->assertJson(['user' => 'anonymous', 'bearer' => 'own-secret']);
    });

    it('does not let a session user through as anybody but the anonymous user', function (): void {
        CrudApiExposure::enable();
        apiAccessAnonymous();
        $user = apiAccessUserWithGrant();

        $this->actingAs($user)->postJson('/api/v1/_probe/self', [], apiAccessBearer('own-secret'))
            ->assertOk()
            ->assertJson(['user' => 'anonymous']);
    });

    it('gives the same bearer a 401 on a route that is not marked', function (): void {
        CrudApiExposure::enable();
        apiAccessAnonymous();

        $this->postJson('/api/v1/_probe/plain', [], apiAccessBearer('own-secret'))->assertUnauthorized();
    });

    it('still rate limits a marked route per client address', function (): void {
        CrudApiExposure::enable();
        apiAccessAnonymous();
        config()->set('core.api.rate_limit_per_minute', 2);

        foreach (range(1, 2) as $_) {
            $this->postJson('/api/v1/_probe/self', [], apiAccessBearer('own-secret'))->assertOk();
        }

        $this->postJson('/api/v1/_probe/self', [], apiAccessBearer('own-secret'))->assertStatus(429);
    });

    it('is declared on the provider callbacks of ERP and on the MES machine ingest', function (): void {
        $marked = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn (Illuminate\Routing\Route $route): bool => in_array(SelfAuthenticatedApiRoute::class, Route::gatherRouteMiddleware($route), true))
            ->map(static fn (Illuminate\Routing\Route $route): string => $route->uri())
            ->filter(static fn (string $uri): bool => ! str_contains($uri, '_probe'))
            ->sort()
            ->values()
            ->all();

        expect($marked)->toBe([
            'api/v1/erp/einvoice/{provider}/callbacks',
            'api/v1/erp/payment-requests/{provider}/callbacks',
            'api/v1/mes/machine-data',
        ]);
    });
});
