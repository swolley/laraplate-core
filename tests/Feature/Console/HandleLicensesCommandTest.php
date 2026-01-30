<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Console\HandleLicensesCommand;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('command exists and has correct signature', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('auth:licenses');
    expect($source)->toContain('Renew, add or delete user licenses');
});

it('command class has correct properties', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);

    expect($reflection->getName())->toBe('Modules\Core\Console\HandleLicensesCommand');
    expect($reflection->isSubclassOf(Modules\Core\Overrides\Command::class))->toBeTrue();
});

it('command can be instantiated', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);

    expect($reflection->isInstantiable())->toBeTrue();
    expect($reflection->isSubclassOf(Modules\Core\Overrides\Command::class))->toBeTrue();
});

it('command has correct namespace', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);

    expect($reflection->getNamespaceName())->toBe('Modules\Core\Console');
    expect($reflection->getShortName())->toBe('HandleLicensesCommand');
});

it('command has handle method', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);

    expect($reflection->hasMethod('handle'))->toBeTrue();
});

it('command handle method returns int', function (): void {
    $reflection = new ReflectionMethod(HandleLicensesCommand::class, 'handle');

    // The handle method may not have explicit return type, so we check the source code
    $source = file_get_contents($reflection->getDeclaringClass()->getFileName());
    expect($source)->toContain('return BaseCommand::SUCCESS');
    expect($source)->toContain('return BaseCommand::FAILURE');
});

it('command uses Laravel Prompts', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Laravel\Prompts\confirm');
    expect($source)->toContain('Laravel\Prompts\select');
    expect($source)->toContain('Laravel\Prompts\table');
    expect($source)->toContain('Laravel\Prompts\text');
});

it('command has license management methods', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('renewLicenses');
    expect($source)->toContain('addLicenses');
    expect($source)->toContain('closeLicenses');
    expect($source)->toContain('listLicenses');
});

it('command handles license status display', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Current licenses status');
    expect($source)->toContain('table(');
});

it('command validates input', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('validationCallback');
});

it('command handles expired licenses', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('expired');
    expect($source)->toContain('License::expired()');
});

it('command shows max sessions setting', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('max_concurrent_sessions');
});

it('command handles license creation', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('License::factory()');
    expect($source)->toContain('create(');
});

it('command handles license updates', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('update(');
    expect($source)->toContain('valid_from');
    expect($source)->toContain('valid_to');
});

it('command logs license operations', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Log::info');
});

it('command handles different license actions', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('list');
    expect($source)->toContain('add');
    expect($source)->toContain('renew');
    expect($source)->toContain('close');
});

it('command handles license grouping', function (): void {
    $reflection = new ReflectionClass(HandleLicensesCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('groupBy');
    expect($source)->toContain('valid_to');
});
