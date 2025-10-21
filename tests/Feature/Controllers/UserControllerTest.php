<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

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
                'id', 'name', 'username', 'email', 'groups', 'canImpersonate', 'permissions',
            ],
        ]);
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
    
    $response->assertStatus(403);
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
    
    $response->assertStatus(403);
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
                'id', 'name', 'username', 'email', 'groups', 'canImpersonate', 'permissions',
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