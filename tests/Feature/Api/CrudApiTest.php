<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Inspector\Entities\Table;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Models\DynamicEntity;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

beforeEach(function (): void {
    Config::set('core.expose_crud_api', true);
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::findOrCreate('superadmin', 'web'));
    $this->actingAs($this->user);
});

// Verify SQLite + Inspector compatibility and model resolution (LaravelTestCase populates HelpersCache so models() works).
test('inspector returns users table with SQLite and tryResolveModel returns User', function (): void {
    $inspected = SchemaInspector::getInstance()->table('users', null);
    expect($inspected)->toBeInstanceOf(Table::class)
        ->and($inspected->columns->isNotEmpty())->toBeTrue('Inspector should return columns for users table');

    $resolved = DynamicEntity::tryResolveModel('users', null);
    /** @var class-string<\Illuminate\Database\Eloquent\Model> $expected_user_model */
    $expected_user_model = config('auth.providers.users.model');
    expect($resolved)->toBe($expected_user_model);
});

// Search API disabled: route and controller method commented out.
// test('api search returns data for valid entity', function (): void {
//     $response = $this->getJson(route('core.api.search', ['entity' => 'users']));
//     $response->assertStatus(200)
//         ->assertJsonStructure([
//             'data' => [
//                 '*' => [
//                     'id', 'name', 'email', 'created_at', 'updated_at',
//                 ],
//             ],
//         ]);
// });
// test('api search returns empty data for invalid entity', function (): void {
//     $response = $this->getJson(route('core.api.search', ['entity' => 'invalid']));
//     $response->assertStatus(200)->assertJson(['data' => []]);
// });
// test('api search supports pagination', function (): void {
//     User::factory()->count(5)->create();
//     $response = $this->getJson(route('core.api.search', ['entity' => 'users', 'page' => 1, 'per_page' => 3]));
//     $response->assertStatus(200)->assertJsonStructure(['data' => [], 'meta' => ['current_page', 'per_page', 'total']]);
// });
// test('api search supports filtering', function (): void {
//     User::factory()->create(['name' => 'John Doe']);
//     User::factory()->create(['name' => 'Jane Smith']);
//     $response = $this->getJson(route('core.api.search', [
//         'entity' => 'users',
//         'filters' => [['property' => 'name', 'value' => 'John', 'operator' => 'contains']],
//     ]));
//     $response->assertStatus(200);
//     $data = $response->json('data');
//     expect($data)->toHaveCount(1);
//     expect($data[0]['name'])->toBe('John Doe');
// });
// test('api search supports sorting', function (): void {
//     User::factory()->create(['name' => 'Z User']);
//     User::factory()->create(['name' => 'A User']);
//     $response = $this->getJson(route('core.api.search', ['entity' => 'users', 'sort' => 'name', 'order' => 'asc']));
//     $response->assertStatus(200);
//     $data = $response->json('data');
//     expect($data)->toHaveCount(3);
//     expect($data[0]['name'])->toBe('A User');
// });

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
    Password::defaults(static fn () => Password::min(8)->letters()->mixedCase()->numbers()->symbols());

    $userData = [
        'name' => 'New User',
        'username' => 'newuser',
        'email' => 'new@example.com',
        'password' => 'Aa1!VeryUniqueTestPass',
    ];

    $response = $this->postJson(route('core.api.insert', ['entity' => 'users']), $userData);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'email',
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

test('api insert rejects weak password and does not create record', function (): void {
    Password::defaults(static fn () => Password::min(8)->letters()->mixedCase()->numbers()->symbols());

    $userData = [
        'name' => 'Weak Password User',
        'username' => 'weakpassworduser',
        'email' => 'weak@example.com',
        'password' => 'password',
    ];

    $response = $this->postJson(route('core.api.insert', ['entity' => 'users']), $userData);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    $this->assertDatabaseMissing('users', [
        'email' => 'weak@example.com',
    ]);
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

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toBeArray()->toHaveCount(1);
    expect($data[0])->toMatchArray([
        'id' => $this->user->id,
        'name' => 'Updated User',
        'email' => 'updated@example.com',
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
    $response = $this->getJson(route('core.api.tree', ['entity' => 'roles']));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'name',
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
            'data' => [
                'record',
                'history',
            ],
        ]);
});

// test('api search handles complex filters', ...);
// test('api search handles multiple sort fields', ...);
// test('api search handles limit parameter', ...);
// test('api search handles offset parameter', ...);
// test('api search returns proper error for invalid entity', ...);
// test('api search handles empty results gracefully', ...);
