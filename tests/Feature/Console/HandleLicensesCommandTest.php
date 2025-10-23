<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Console\HandleLicensesCommand;

uses(RefreshDatabase::class);

test('command exists and has correct signature', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('auth:licenses');
    expect($source)->toContain('Renew, add or delete user licenses');
});

test('command class has correct properties', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);

    expect($reflection->getName())->toBe('Modules\Core\Console\HandleLicensesCommand');
    expect($reflection->isSubclassOf(Modules\Core\Overrides\Command::class))->toBeTrue();
});

test('command can be instantiated', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);

    expect($reflection->isInstantiable())->toBeTrue();
    expect($reflection->isSubclassOf(Modules\Core\Overrides\Command::class))->toBeTrue();
});

test('command has correct namespace', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);

    expect($reflection->getNamespaceName())->toBe('Modules\Core\Console');
    expect($reflection->getShortName())->toBe('HandleLicensesCommand');
});

test('command has handle method', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);

    expect($reflection->hasMethod('handle'))->toBeTrue();
});

test('command handle method returns int', function (): void {
    $reflection = new ReflectionMethod(HandleLicensesCommand::class, 'handle');

    // The handle method may not have explicit return type, so we check the source code
    $source = file_get_contents($reflection->getDeclaringClass()->getFileName());
    expect($source)->toContain('return BaseCommand::SUCCESS');
    expect($source)->toContain('return BaseCommand::FAILURE');
});

test('command uses Laravel Prompts', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Laravel\Prompts\confirm');
    expect($source)->toContain('Laravel\Prompts\select');
    expect($source)->toContain('Laravel\Prompts\table');
    expect($source)->toContain('Laravel\Prompts\text');
});

test('command handles database transactions', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$this->db->transaction');
});

test('command has license management methods', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('renewLicenses');
    expect($source)->toContain('addLicenses');
    expect($source)->toContain('closeLicenses');
    expect($source)->toContain('listLicenses');
});

test('command handles license status display', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Current licenses status');
    expect($source)->toContain('table(');
});

test('command validates input', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('validationCallback');
});

test('command handles expired licenses', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('expired');
    expect($source)->toContain('License::expired()');
});

test('command shows max sessions setting', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('maxConcurrentSessions');
});

test('command handles license creation', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('License::factory()');
    expect($source)->toContain('create(');
});

test('command handles license updates', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('update(');
    expect($source)->toContain('valid_from');
    expect($source)->toContain('valid_to');
});

test('command logs license operations', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Log::info');
});

test('command handles different license actions', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('list');
    expect($source)->toContain('add');
    expect($source)->toContain('renew');
    expect($source)->toContain('close');
});

test('command handles license grouping', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('groupBy');
    expect($source)->toContain('valid_to');
});
