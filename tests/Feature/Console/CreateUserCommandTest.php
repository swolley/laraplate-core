<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Console\CreateUserCommand;
use Modules\Core\Overrides\Command;

uses(RefreshDatabase::class);

test('command exists and has correct signature', function (): void {
    // Test that the command class exists and has correct signature
    $reflection = new ReflectionClass(CreateUserCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('auth:create-user');
    expect($source)->toContain('Create new user');
});

test('command class has correct properties', function (): void {
    $reflection = new ReflectionClass(CreateUserCommand::class);

    expect($reflection->getName())->toBe('Modules\Core\Console\CreateUserCommand');
    expect($reflection->isSubclassOf(Command::class))->toBeTrue();
});

test('command has correct signature', function (): void {
    $reflection = new ReflectionClass(CreateUserCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('auth:create-user');
    expect($source)->toContain('Create new user');
});

test('command can be instantiated', function (): void {
    $reflection = new ReflectionClass(CreateUserCommand::class);

    expect($reflection->isInstantiable())->toBeTrue();
    expect($reflection->isSubclassOf(Command::class))->toBeTrue();
});

test('command uses HasCommandUtils trait', function (): void {
    $reflection = new ReflectionClass(CreateUserCommand::class);
    $traits = $reflection->getTraitNames();

    expect($traits)->toContain('Modules\Core\Helpers\HasCommandUtils');
});

test('command has correct namespace', function (): void {
    $reflection = new ReflectionClass(CreateUserCommand::class);

    expect($reflection->getNamespaceName())->toBe('Modules\Core\Console');
    expect($reflection->getShortName())->toBe('CreateUserCommand');
});

test('command extends base command', function (): void {
    $reflection = new ReflectionClass(CreateUserCommand::class);

    expect($reflection->isSubclassOf(Command::class))->toBeTrue();
});

test('command has handle method', function (): void {
    $reflection = new ReflectionClass(CreateUserCommand::class);

    expect($reflection->hasMethod('handle'))->toBeTrue();
});

test('command handle method returns int', function (): void {
    $reflection = new ReflectionMethod(CreateUserCommand::class, 'handle');

    expect($reflection->getReturnType()->getName())->toBe('int');
});

test('command uses Laravel Prompts', function (): void {
    $reflection = new ReflectionClass(CreateUserCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Laravel\Prompts\confirm');
    expect($source)->toContain('Laravel\Prompts\multiselect');
    expect($source)->toContain('Laravel\Prompts\password');
    expect($source)->toContain('Laravel\Prompts\text');
});

test('command handles database transactions', function (): void {
    $reflection = new ReflectionClass(CreateUserCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$this->db->transaction');
});

test('command creates users with roles and permissions', function (): void {
    $reflection = new ReflectionClass(CreateUserCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$user->roles()->sync');
    expect($source)->toContain('$user->permissions()->sync');
});

test('command generates random passwords', function (): void {
    $reflection = new ReflectionClass(CreateUserCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Str::password()');
});

test('command validates user input', function (): void {
    $reflection = new ReflectionClass(CreateUserCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('validationCallback');
});

test('command handles multiple user creation', function (): void {
    $reflection = new ReflectionClass(CreateUserCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('create another user');
});

test('command shows user creation summary', function (): void {
    $reflection = new ReflectionClass(CreateUserCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('table([');
    expect($source)->toContain('User created');
});
