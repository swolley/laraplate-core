<?php

declare(strict_types=1);

use Modules\Core\Actions\Fortify\CreateNewUser;
use Modules\Core\Models\User;


it('creates user with valid input', function (): void {
    $action = new CreateNewUser;
    $password = 'K9#mP' . bin2hex(random_bytes(12)) . 'xQ!2';

    $user = $action->create([
        'name' => 'New User',
        'username' => 'newuser',
        'email' => 'newuser@example.com',
        'password' => $password,
        'password_confirmation' => $password,
    ]);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->toBe('New User')
        ->and($user->email)->toBe('newuser@example.com')
        ->and($user->username)->toBe('newuser');
});
