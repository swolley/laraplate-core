<?php

declare(strict_types=1);

use Illuminate\Database\DatabaseManager;
use Modules\Core\Console\CreateUserCommand;

it('creates a new user', function (): void {
    $db = $this->createMock(DatabaseManager::class);
    $command = new CreateUserCommand($db);

    $user = user_class()::factory()->create();

    $commandTester = $this->executeCommand($command, ['command' => 'auth:create-user']);

    $this->assertDatabaseHas('users', ['email' => $user->email]);

    $output = $commandTester->getDisplay();
    expect($output)->toContain('Created 1 users');
});

it('fails to create a new user with invalid input', function (): void {
    $db = $this->createMock(DatabaseManager::class);
    $command = new CreateUserCommand($db);

    $user = user_class()::factory()->create();

    $commandTester = $this->executeCommand($command, ['command' => 'auth:create-user']);
    $output = $commandTester->getDisplay();
    expect($output)->toContain('Whoops, something went wrong!');
})->throws(Throwable::class);
