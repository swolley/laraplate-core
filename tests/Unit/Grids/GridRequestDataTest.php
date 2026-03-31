<?php

declare(strict_types=1);

use Modules\Core\Grids\Casts\GridAction;
use Modules\Core\Grids\Casts\GridRequestData;
use Modules\Core\Grids\Requests\GridRequest;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('currently throws due request type mismatch in parent constructor', function (): void {
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

    expect(fn (): GridRequestData => new GridRequestData(
        GridAction::SELECT,
        $request,
        'users',
        $validated,
        'user.id',
    ))->toThrow(TypeError::class);
});

it('throws for write action with same mismatch', function (): void {
    $request = GridRequest::create('/api/v1/core/users/update', 'POST', [
        'user_id' => '12',
    ]);

    $validated = [
        'pagination' => 25,
        'changes' => [
            'name' => 'Updated Name',
        ],
    ];

    expect(fn (): GridRequestData => new GridRequestData(
        GridAction::UPDATE,
        $request,
        'users',
        $validated,
        'user.id',
    ))->toThrow(TypeError::class);
});

it('throws type error before primary key validation branch', function (): void {
    $request = GridRequest::create('/api/v1/core/users/update', 'POST');

    expect(fn (): GridRequestData => new GridRequestData(
        GridAction::UPDATE,
        $request,
        'users',
        ['pagination' => 25, 'changes' => ['name' => 'No PK']],
        [],
    ))->toThrow(TypeError::class);
});
