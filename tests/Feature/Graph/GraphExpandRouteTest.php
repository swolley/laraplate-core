<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Modules\Core\Models\User;

uses(RefreshDatabase::class);

it('registers the api graph expand route under crud', function (): void {
    Config::set('core.expose_crud_api', true);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/crud/graph/expand/Core/users/' . $user->getKey())
        ->assertStatus(401);
});

it('registers the api graph search route under crud', function (): void {
    Config::set('core.expose_crud_api', true);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/crud/graph/search/Core/users?qs=alice')
        ->assertStatus(400);
});

it('registers the web graph expand route under app crud', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/app/crud/graph/expand/Core/users/' . $user->getKey())
        ->assertStatus(401);
});

it('registers the web graph search route under app crud', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/app/crud/graph/search/Core/users?qs=alice')
        ->assertStatus(400);
});
