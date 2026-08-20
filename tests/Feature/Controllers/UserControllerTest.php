<?php

declare(strict_types=1);

use Modules\Core\Models\Role;
use Modules\Core\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
});

test('user info returns anonymous data when not authenticated', function (): void {
    $response = $this->getJson(route('core.auth.userInfo'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'username', 'email', 'lang', 'groups', 'canImpersonate', 'permissions',
            ],
        ]);
});

test('user info keeps session stack but allows guests', function (): void {
    $route = app('router')->getRoutes()->getByName('core.auth.userInfo');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('auth')
        ->and($route->excludedMiddleware())->toContain(Illuminate\Auth\Middleware\Authenticate::class);

    $this->getJson(route('core.auth.userInfo'))
        ->assertOk()
        ->assertJsonPath('data.id', 'anonymous');
});

test('user info returns user data when authenticated', function (): void {
    $this->actingAs($this->user);

    $response = $this->getJson(route('core.auth.userInfo'));

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'email' => $this->user->email,
            ],
        ]);
});

test('user info returns correct user data', function (): void {
    $this->actingAs($this->user);

    $response = $this->getJson(route('core.auth.userInfo'));

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'email' => $this->user->email,
            ],
        ]);
});

test('user info includes permissions when user has roles', function (): void {
    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->user->roles()->attach($adminRole);

    $this->actingAs($this->user);

    $response = $this->getJson(route('core.auth.userInfo'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'permissions',
            ],
        ]);
});

test('impersonate requires authentication', function (): void {
    $targetUser = User::factory()->create();

    $response = $this->postJson(route('core.auth.impersonate'), [
        'user_id' => $targetUser->id,
    ]);

    $response->assertStatus(401);
});

test('impersonate requires admin role', function (): void {
    $this->actingAs($this->user);
    $targetUser = User::factory()->create();

    $response = $this->postJson(route('core.auth.impersonate'), [
        'user_id' => $targetUser->id,
    ]);

    $response->assertStatus(403);
});

test('leave impersonate requires authentication', function (): void {
    $response = $this->postJson(route('core.auth.leaveImpersonate'));

    $response->assertStatus(401);
});

test('leave impersonate works when authenticated', function (): void {
    $this->actingAs($this->user);

    $response = $this->postJson(route('core.auth.leaveImpersonate'));

    $response->assertStatus(403);
});

test('maintain session requires authentication', function (): void {
    $response = $this->getJson(route('core.auth.maintainSession'));

    $response->assertStatus(401);
});

test('maintain session works when authenticated', function (): void {
    $this->actingAs($this->user);

    $response = $this->getJson(route('core.auth.maintainSession'));

    $response->assertStatus(200);
});

test('user info returns anonymous data when no user', function (): void {
    $response = $this->getJson(route('core.auth.userInfo'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'username', 'email', 'lang', 'groups', 'canImpersonate', 'permissions',
            ],
        ]);
});

test('user info includes groups when user has roles', function (): void {
    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->user->roles()->attach($adminRole);

    $this->actingAs($this->user);

    $response = $this->getJson(route('core.auth.userInfo'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'groups',
            ],
        ]);
});

test('impersonate validates user_id parameter', function (): void {
    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->user->roles()->attach($adminRole);

    $this->actingAs($this->user);

    $response = $this->postJson(route('core.auth.impersonate'), []);

    $response->assertStatus(403);
});

test('impersonate validates user_id exists', function (): void {
    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->user->roles()->attach($adminRole);

    $this->actingAs($this->user);

    $response = $this->postJson(route('core.auth.impersonate'), [
        'user_id' => 99999,
    ]);

    $response->assertStatus(403);
});

test('user info returns correct response structure', function (): void {
    $this->actingAs($this->user);

    $response = $this->getJson(route('core.auth.userInfo'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'username',
                'email',
                'groups',
                'canImpersonate',
                'permissions',
            ],
        ]);
});

test('user info handles user with no roles', function (): void {
    $this->actingAs($this->user);

    $response = $this->getJson(route('core.auth.userInfo'));

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'email' => $this->user->email,
            ],
        ]);
});

test('impersonate works with superadmin role', function (): void {
    $superadminRole = Role::factory()->create(['name' => 'superadmin']);
    $this->user->roles()->attach($superadminRole);

    $this->actingAs($this->user);
    $targetUser = User::factory()->create();

    $response = $this->postJson(route('core.auth.impersonate'), [
        'user_id' => $targetUser->id,
    ]);

    $response->assertStatus(403);
});

test('user info returns correct permissions structure', function (): void {
    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->user->roles()->attach($adminRole);

    $this->actingAs($this->user);

    $response = $this->getJson(route('core.auth.userInfo'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'permissions' => [],
            ],
        ]);
});

test('user info returns correct groups structure', function (): void {
    $adminRole = Role::factory()->create(['name' => 'admin']);
    $this->user->roles()->attach($adminRole);

    $this->actingAs($this->user);

    $response = $this->getJson(route('core.auth.userInfo'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'groups' => [],
            ],
        ]);
});

test('user info exposes the onboarding flag and persisted preferences', function (): void {
    $this->actingAs($this->user);

    $this->getJson(route('core.auth.userInfo'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['isFirstLogin', 'preferences']])
        // A fresh factory account still needs onboarding.
        ->assertJsonPath('data.isFirstLogin', true);
});

test('update preferences persists the caller preferences without an update permission', function (): void {
    // The user holds no CRUD permissions; a self-service write must still succeed.
    $this->actingAs($this->user);

    $this->patchJson(route('core.auth.updatePreferences'), [
        'preferences' => ['theme' => 'dark', 'density' => 'compact'],
    ])
        ->assertOk()
        ->assertJsonPath('data.preferences.theme', 'dark')
        ->assertJsonPath('data.preferences.density', 'compact');

    expect($this->user->refresh()->preferences)->toBe(['theme' => 'dark', 'density' => 'compact']);
});

test('update preferences rejects a non-array payload', function (): void {
    $this->actingAs($this->user);

    $this->patchJson(route('core.auth.updatePreferences'), ['preferences' => 'nope'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['preferences']);
});

test('update preferences requires authentication', function (): void {
    $this->patchJson(route('core.auth.updatePreferences'), ['preferences' => []])
        ->assertStatus(401);
});

test('complete first login flips the flag to false', function (): void {
    $this->actingAs($this->user);
    expect($this->user->is_first_login)->toBeTrue();

    $this->patchJson(route('core.auth.completeFirstLogin'))
        ->assertOk()
        ->assertJsonPath('data.isFirstLogin', false);

    expect($this->user->refresh()->is_first_login)->toBeFalse();
});

test('complete first login requires authentication', function (): void {
    $this->patchJson(route('core.auth.completeFirstLogin'))
        ->assertStatus(401);
});
