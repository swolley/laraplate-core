<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Support\CrudApiExposure;

const API_TOKEN_TEST_PERMISSION = 'default.users.select';

beforeEach(function (): void {
    Cache::flush();
    RateLimiter::clear('api');
    config()->set('permission.roles.superadmin', 'superadmin');
    config()->set('core.api.rate_limit_per_minute', 600);
});

/**
 * The permission exists for both guards, as the seeder leaves it.
 */
function apiTokenTestPermission(string $name): void
{
    foreach (['web', 'api'] as $guard) {
        Permission::query()->firstOrCreate(['name' => $name, 'guard_name' => $guard]);
    }
}

/**
 * A user of the class the auth provider hands out, holding the given api permissions through one api role.
 *
 * @param  list<string>  $permissions
 */
function apiTokenTestUser(array $permissions = [API_TOKEN_TEST_PERMISSION]): App\Models\User
{
    $role = Role::factory()->create(['name' => 'api_role_' . uniqid(), 'guard_name' => 'api']);

    foreach ($permissions as $name) {
        apiTokenTestPermission($name);
        $role->givePermissionTo(Permission::query()->where(['name' => $name, 'guard_name' => 'api'])->firstOrFail());
    }

    $user = App\Models\User::query()->findOrFail(User::factory()->create()->getKey());
    $user->assignRole($role);

    return $user->fresh();
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function apiTokenTestPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'ci',
        'abilities' => [API_TOKEN_TEST_PERMISSION],
        'expires_at' => now()->addDays(30)->toIso8601String(),
    ], $overrides);
}

describe('creating a personal token', function (): void {
    it('returns the plain token once, hashed in storage, with the abilities and expiry', function (): void {
        $user = apiTokenTestUser();

        $response = $this->actingAs($user)->postJson(route('core.auth.tokens.create'), apiTokenTestPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'ci')
            ->assertJsonPath('data.abilities', [API_TOKEN_TEST_PERMISSION]);

        $plain = $response->json('data.token');
        $stored = PersonalAccessToken::query()->sole();

        expect($plain)->toBeString()->toContain('|')
            ->and($stored->token)->not->toBe($plain)
            ->and(str_contains($stored->token, explode('|', $plain, 2)[1]))->toBeFalse()
            ->and($stored->tokenable_type)->toBe(App\Models\User::class)
            ->and((string) $stored->tokenable_id)->toBe((string) $user->getKey())
            ->and($stored->expires_at)->not->toBeNull()
            ->and(PersonalAccessToken::findToken($plain)?->is($stored))->toBeTrue();
    });

    it('creates a token that authenticates on the api', function (): void {
        CrudApiExposure::enable();
        $user = apiTokenTestUser();

        $plain = $this->actingAs($user)->postJson(route('core.auth.tokens.create'), apiTokenTestPayload())
            ->assertCreated()->json('data.token');

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/select/core/users', ['Authorization' => 'Bearer ' . $plain])->assertOk();
    });

    it('accepts an ability the user holds through an ancestor role', function (): void {
        $permission = 'default.roles.select';
        apiTokenTestPermission($permission);
        $parent = Role::factory()->create(['name' => 'api_parent', 'guard_name' => 'api']);
        $parent->givePermissionTo(Permission::query()->where(['name' => $permission, 'guard_name' => 'api'])->firstOrFail());
        $child = Role::factory()->create(['name' => 'api_child', 'guard_name' => 'api']);
        $child->parent_id = $parent->id;
        $child->save();

        $user = App\Models\User::query()->findOrFail(User::factory()->create()->getKey());
        $user->assignRole($child);

        $this->actingAs($user->fresh())
            ->postJson(route('core.auth.tokens.create'), apiTokenTestPayload(['abilities' => [$permission]]))
            ->assertCreated();
    });

    it('stores the allowed networks of the token', function (): void {
        $user = apiTokenTestUser();

        $this->actingAs($user)
            ->postJson(route('core.auth.tokens.create'), apiTokenTestPayload(['allowed_cidrs' => ['10.0.0.0/8', '192.168.1.0/24']]))
            ->assertCreated();

        expect(json_decode((string) PersonalAccessToken::query()->sole()->getRawOriginal('allowed_cidrs'), true))
            ->toBe(['10.0.0.0/8', '192.168.1.0/24']);
    });

    it('refuses a malformed network', function (): void {
        $user = apiTokenTestUser();

        $this->actingAs($user)
            ->postJson(route('core.auth.tokens.create'), apiTokenTestPayload(['allowed_cidrs' => ['not-a-network']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['allowed_cidrs.0']);
    });

    it('refuses abilities the roles of the user do not grant', function (array $abilities): void {
        apiTokenTestPermission('default.roles.delete');
        $user = apiTokenTestUser();

        $this->actingAs($user)
            ->postJson(route('core.auth.tokens.create'), apiTokenTestPayload(['abilities' => $abilities]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['abilities']);

        expect(PersonalAccessToken::query()->count())->toBe(0);
    })->with([
        'a permission outside the roles' => [['default.roles.delete']],
        'a granted one next to a foreign one' => [[API_TOKEN_TEST_PERMISSION, 'default.roles.delete']],
        'the wildcard' => [['*']],
        'a name that is no permission' => [['nope.nothing.select']],
        'no ability at all' => [[]],
    ]);

    it('refuses a permission granted only on the web guard', function (): void {
        apiTokenTestPermission('default.roles.update');
        $web_role = Role::factory()->create(['name' => 'web_only', 'guard_name' => 'web']);
        $web_role->givePermissionTo(Permission::query()->where(['name' => 'default.roles.update', 'guard_name' => 'web'])->firstOrFail());
        $user = apiTokenTestUser();
        $user->assignRole($web_role);

        $this->actingAs($user->fresh())
            ->postJson(route('core.auth.tokens.create'), apiTokenTestPayload(['abilities' => ['default.roles.update']]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['abilities']);
    });

    it('refuses an expiry beyond the longest life, or none, for a personal token', function (?string $expiry): void {
        config()->set('core.api_tokens.max_lifetime_days', 365);
        $user = apiTokenTestUser();
        $payload = apiTokenTestPayload();
        unset($payload['expires_at']);

        if ($expiry !== null) {
            $payload['expires_at'] = now()->modify($expiry)->toIso8601String();
        }

        $this->actingAs($user)
            ->postJson(route('core.auth.tokens.create'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['expires_at']);

        expect(PersonalAccessToken::query()->count())->toBe(0);
    })->with([
        'none' => [null],
        'beyond the limit' => ['+366 days'],
        'in the past' => ['-1 day'],
    ]);

    it('accepts an expiry at the limit and follows the configured limit', function (): void {
        $user = apiTokenTestUser();

        $this->actingAs($user)
            ->postJson(route('core.auth.tokens.create'), apiTokenTestPayload(['expires_at' => now()->addDays(364)->toIso8601String()]))
            ->assertCreated();

        config()->set('core.api_tokens.max_lifetime_days', 7);

        $this->actingAs($user)
            ->postJson(route('core.auth.tokens.create'), apiTokenTestPayload(['expires_at' => now()->addDays(30)->toIso8601String()]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['expires_at']);
    });

    it('stores the instant that was asked for, whatever offset the client sends', function (string $offset): void {
        $asked = now()->addDays(30)->startOfSecond();
        $user = apiTokenTestUser();

        $this->actingAs($user)
            ->postJson(route('core.auth.tokens.create'), apiTokenTestPayload(['expires_at' => $asked->copy()->setTimezone($offset)->format('Y-m-d\\TH:i:sP')]))
            ->assertCreated();

        expect(PersonalAccessToken::query()->sole()->expires_at->getTimestamp())->toBe($asked->getTimestamp());
    })->with(['Z' => ['UTC'], 'east' => ['Asia/Tokyo'], 'far east' => ['Pacific/Kiritimati'], 'west' => ['America/Los_Angeles']]);

    it('refuses an expiry beyond the longest life in any offset', function (string $offset): void {
        $user = apiTokenTestUser();
        $beyond = now()->addDays(365)->addHour();

        $this->actingAs($user)
            ->postJson(route('core.auth.tokens.create'), apiTokenTestPayload(['expires_at' => $beyond->copy()->setTimezone($offset)->format('Y-m-d\\TH:i:sP')]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['expires_at']);

        expect(PersonalAccessToken::query()->count())->toBe(0);
    })->with(['Z' => ['UTC'], 'east' => ['Asia/Tokyo'], 'far east' => ['Pacific/Kiritimati'], 'west' => ['America/Los_Angeles']]);

    it('cannot be reached with a bearer token', function (): void {
        $user = apiTokenTestUser();
        $new = $user->createToken('bearer', [API_TOKEN_TEST_PERMISSION], now()->addDays(10));

        $this->postJson(route('core.auth.tokens.create'), apiTokenTestPayload(), ['Authorization' => 'Bearer ' . $new->plainTextToken])
            ->assertUnauthorized();

        expect(PersonalAccessToken::query()->count())->toBe(1);
    });

    it('refuses a superadmin', function (): void {
        $user = apiTokenTestUser();
        $user->assignRole(Role::findOrCreate('superadmin', 'web'));

        $this->actingAs($user->fresh())
            ->postJson(route('core.auth.tokens.create'), apiTokenTestPayload())
            ->assertForbidden();

        expect(PersonalAccessToken::query()->count())->toBe(0);
    });

    it('needs a session', function (): void {
        $this->postJson(route('core.auth.tokens.create'), apiTokenTestPayload())->assertUnauthorized();
        $this->getJson(route('core.auth.tokens.list'))->assertUnauthorized();
    });
});

describe('listing and revoking', function (): void {
    it('lists the own tokens with name, abilities, last use and expiry, never the token', function (): void {
        $user = apiTokenTestUser();
        $other = apiTokenTestUser();
        $mine = $user->createToken('mine', [API_TOKEN_TEST_PERMISSION], now()->addDays(10));
        $mine->accessToken->forceFill(['last_used_at' => now()->subHour()])->save();
        $other->createToken('theirs', [API_TOKEN_TEST_PERMISSION], now()->addDays(10));

        $response = $this->actingAs($user)->getJson(route('core.auth.tokens.list'))->assertOk();

        $rows = $response->json('data');

        expect($rows)->toHaveCount(1)
            ->and($rows[0])->toHaveKeys(['id', 'name', 'abilities', 'last_used_at', 'expires_at'])
            ->and($rows[0]['name'])->toBe('mine')
            ->and($rows[0]['abilities'])->toBe([API_TOKEN_TEST_PERMISSION])
            ->and($rows[0]['last_used_at'])->not->toBeNull()
            ->and($rows[0]['expires_at'])->not->toBeNull()
            ->and($rows[0])->not->toHaveKey('token')
            ->and($response->getContent())->not->toContain(explode('|', $mine->plainTextToken, 2)[1]);
    });

    it('revokes a token so the next api call is a 401', function (): void {
        CrudApiExposure::enable();
        $user = apiTokenTestUser();
        $new = $user->createToken('doomed', [API_TOKEN_TEST_PERMISSION], now()->addDays(10));
        $header = ['Authorization' => 'Bearer ' . $new->plainTextToken];

        $this->getJson('/api/v1/select/core/users', $header)->assertOk();

        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
        $this->actingAs($user, 'web')->deleteJson(route('core.auth.tokens.revoke', ['token' => $new->accessToken->getKey()]))->assertNoContent();

        expect(PersonalAccessToken::query()->count())->toBe(0);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/select/core/users', $header)->assertUnauthorized();
    });

    it('does not reveal or revoke the token of someone else', function (): void {
        $user = apiTokenTestUser();
        $other = apiTokenTestUser();
        $theirs = $other->createToken('theirs', [API_TOKEN_TEST_PERMISSION], now()->addDays(10));

        $this->actingAs($user)->deleteJson(route('core.auth.tokens.revoke', ['token' => $theirs->accessToken->getKey()]))->assertNotFound();

        expect(PersonalAccessToken::query()->count())->toBe(1);
    });
});
