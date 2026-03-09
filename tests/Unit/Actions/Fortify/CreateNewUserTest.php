<?php

declare(strict_types=1);

use Modules\Core\Actions\Fortify\CreateNewUser;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('creates user with valid input', function (): void {
    $action = new CreateNewUser;

    $user = $action->create([
        'name' => 'New User',
        'username' => 'newuser',
        'email' => 'newuser@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->toBe('New User')
        ->and($user->email)->toBe('newuser@example.com')
        ->and($user->username)->toBe('newuser');
});
