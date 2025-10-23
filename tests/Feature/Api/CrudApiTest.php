<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('api search returns data for valid entity', function (): void {
    $response = $this->getJson(route('core.api.search', ['entity' => 'users']));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'name', 'email', 'created_at', 'updated_at',
                ],
            ],
        ]);
});

test('api search returns empty data for invalid entity', function (): void {
    $response = $this->getJson(route('core.api.search', ['entity' => 'invalid']));

    $response->assertStatus(200)
        ->assertJson([
            'data' => [],
        ]);
});

test('api search supports pagination', function (): void {
    User::factory()->count(5)->create();

    $response = $this->getJson(route('core.api.search', [
        'entity' => 'users',
        'page' => 1,
        'per_page' => 3,
    ]));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [],
            'meta' => [
                'current_page',
                'per_page',
                'total',
            ],
        ]);
});

test('api search supports filtering', function (): void {
    User::factory()->create(['name' => 'John Doe']);
    User::factory()->create(['name' => 'Jane Smith']);

    $response = $this->getJson(route('core.api.search', [
        'entity' => 'users',
        'filters' => [
            [
                'property' => 'name',
                'value' => 'John',
                'operator' => 'contains',
            ],
        ],
    ]));

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['name'])->toBe('John Doe');
});

test('api search supports sorting', function (): void {
    User::factory()->create(['name' => 'Z User']);
    User::factory()->create(['name' => 'A User']);

    $response = $this->getJson(route('core.api.search', [
        'entity' => 'users',
        'sort' => 'name',
        'order' => 'asc',
    ]));

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data)->toHaveCount(3); // 2 new + 1 existing
    expect($data[0]['name'])->toBe('A User');
});

test('api select returns data for valid entity', function (): void {
    $response = $this->getJson(route('core.api.list', ['entity' => 'users']));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'name', 'email',
                ],
            ],
        ]);
});

test('api detail returns specific record', function (): void {
    $response = $this->getJson(route('core.api.detail', [
        'entity' => 'users',
        'id' => $this->user->id,
    ]));

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
        ]);
});

test('api detail returns 404 for non-existent record', function (): void {
    $response = $this->getJson(route('core.api.detail', [
        'entity' => 'users',
        'id' => 99999,
    ]));

    $response->assertStatus(404);
});

test('api insert creates new record', function (): void {
    $userData = [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'password',
    ];

    $response = $this->postJson(route('core.api.insert', ['entity' => 'users']), $userData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'email', 'created_at', 'updated_at',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'name' => 'New User',
        'email' => 'new@example.com',
    ]);
});

test('api insert validates required fields', function (): void {
    $response = $this->postJson(route('core.api.insert', ['entity' => 'users']), []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

test('api update modifies existing record', function (): void {
    $updateData = [
        'name' => 'Updated User',
        'email' => 'updated@example.com',
    ];

    $response = $this->putJson(route('core.api.replace', [
        'entity' => 'users',
        'id' => $this->user->id,
    ]), $updateData);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $this->user->id,
                'name' => 'Updated User',
                'email' => 'updated@example.com',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'name' => 'Updated User',
        'email' => 'updated@example.com',
    ]);
});

test('api update returns 404 for non-existent record', function (): void {
    $updateData = [
        'name' => 'Updated User',
    ];

    $response = $this->putJson(route('core.api.replace', [
        'entity' => 'users',
        'id' => 99999,
    ]), $updateData);

    $response->assertStatus(404);
});

test('api delete removes record', function (): void {
    $response = $this->deleteJson(route('core.api.delete', [
        'entity' => 'users',
        'id' => $this->user->id,
    ]));

    $response->assertStatus(200);

    $this->assertDatabaseMissing('users', [
        'id' => $this->user->id,
    ]);
});

test('api delete returns 404 for non-existent record', function (): void {
    $response = $this->deleteJson(route('core.api.delete', [
        'entity' => 'users',
        'id' => 99999,
    ]));

    $response->assertStatus(404);
});

test('api tree returns hierarchical data', function (): void {
    $response = $this->getJson(route('core.api.tree', ['entity' => 'users']));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'name', 'email',
                ],
            ],
        ]);
});

test('api history returns record history', function (): void {
    $response = $this->getJson(route('core.api.history', [
        'entity' => 'users',
        'id' => $this->user->id,
    ]));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [],
        ]);
});

test('api search handles complex filters', function (): void {
    User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
    User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

    $response = $this->getJson(route('core.api.search', [
        'entity' => 'users',
        'filters' => [
            [
                'property' => 'name',
                'value' => 'John',
                'operator' => 'contains',
            ],
            [
                'property' => 'email',
                'value' => 'example.com',
                'operator' => 'contains',
            ],
        ],
    ]));

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['name'])->toBe('John Doe');
});

test('api search handles multiple sort fields', function (): void {
    User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
    User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

    $response = $this->getJson(route('core.api.search', [
        'entity' => 'users',
        'sort' => 'name,email',
        'order' => 'asc,desc',
    ]));

    $response->assertStatus(200);
});

test('api search handles limit parameter', function (): void {
    User::factory()->count(5)->create();

    $response = $this->getJson(route('core.api.search', [
        'entity' => 'users',
        'limit' => 3,
    ]));

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data)->toHaveCount(3);
});

test('api search handles offset parameter', function (): void {
    User::factory()->count(5)->create();

    $response = $this->getJson(route('core.api.search', [
        'entity' => 'users',
        'offset' => 2,
        'limit' => 2,
    ]));

    $response->assertStatus(200);

    $data = $response->json('data');
    expect($data)->toHaveCount(2);
});

test('api search returns proper error for invalid entity', function (): void {
    $response = $this->getJson(route('core.api.search', ['entity' => '']));

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['entity']);
});

test('api search handles empty results gracefully', function (): void {
    $response = $this->getJson(route('core.api.search', [
        'entity' => 'users',
        'filters' => [
            [
                'property' => 'name',
                'value' => 'NonExistent',
                'operator' => 'equals',
            ],
        ],
    ]));

    $response->assertStatus(200)
        ->assertJson([
            'data' => [],
        ]);
});
