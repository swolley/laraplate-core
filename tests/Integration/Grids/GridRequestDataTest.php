<?php

declare(strict_types=1);

use Modules\Core\Grids\Casts\GridAction;
use Modules\Core\Grids\Casts\GridRequestData;
use Modules\Core\Grids\Requests\GridRequest;

it('constructs read request data from grid request', function (): void {
    $request = GridRequest::create('/api/v1/core/users/select', 'GET', [
        'user_id' => '42',
    ]);

    $validated = [
        'pagination' => 25,
        'layout' => ['grid_name' => 'users'],
        'search' => 'john',
        'funnels' => [
            'users.role' => [
                'value' => '["admin","editor"]',
            ],
        ],
        'options' => [
            'users.email' => [
                'value' => 'example.com',
            ],
        ],
    ];

    $data = new GridRequestData(
        GridAction::Select,
        $request,
        'users',
        $validated,
        'user.id',
    );

    expect($data->globalSearch)->toBe('john')
        ->and($data->layout)->toBe(['grid_name' => 'users'])
        ->and($data->funnelsFilters)->toHaveCount(1)
        ->and($data->optionsFilters)->toHaveCount(1);
});

it('constructs write request data from grid request', function (): void {
    $request = GridRequest::create('/api/v1/core/users/update', 'POST', [
        'user_id' => '12',
    ]);

    $validated = [
        'pagination' => 25,
        'changes' => [
            'name' => 'Updated Name',
        ],
    ];

    $data = new GridRequestData(
        GridAction::Update,
        $request,
        'users',
        $validated,
        'user.id',
    );

    expect($data->globalSearch)->toBeNull()
        ->and($data->changes)->toBe(['name' => 'Updated Name']);
});

it('throws when update action has empty primary key', function (): void {
    $request = GridRequest::create('/api/v1/core/users/update', 'POST');

    expect(fn (): GridRequestData => new GridRequestData(
        GridAction::Update,
        $request,
        'users',
        ['pagination' => 25, 'changes' => ['name' => 'No PK']],
        [],
    ))->toThrow(BadMethodCallException::class);
});
