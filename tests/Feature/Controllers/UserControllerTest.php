<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite(); // Disabilita Vite per i test
});

test('user info returns unauthorized when not authenticated', function (): void {
    $response = $this->getJson(route('core.auth.userInfo'));

    $response->assertStatus(401);
});

test('user info returns user data when authenticated', function (): void {
    $user = createTestUser();
    $this->actingAs($user);

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

test('impersonate requires authentication', function (): void {
    $response = $this->postJson(route('core.auth.impersonate'), [
        'user' => 1,
    ]);

    $response->assertStatus(401);
});

test('impersonate requires permission', function (): void {
    $user = createTestUser();
    $this->actingAs($user);

    $response = $this->postJson(route('core.auth.impersonate'), [
        'user' => 2,
    ]);

    $response->assertStatus(403);
});

test('leave impersonate requires authentication', function (): void {
    $response = $this->postJson(route('core.auth.leaveImpersonate'));

    $response->assertStatus(401);
});

test('maintain session returns success when authenticated', function (): void {
    $user = createTestUser();
    $this->actingAs($user);

    $response = $this->getJson(route('core.auth.maintainSession'));

    $response->assertStatus(200)
        ->assertJson(['message' => 'Session maintained successfully.']);
});

test('maintain session returns unauthorized when not authenticated', function (): void {
    $response = $this->getJson(route('core.auth.maintainSession'));

    $response->assertStatus(401)
        ->assertJson(['error' => 'Unauthorized']);
});

// Helper function
function createTestUser(): User
{
    return User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
}
