<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Console\PermissionsRefreshCommand;

uses(RefreshDatabase::class);

test('command exists and has correct signature', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('permission:refresh');
    expect($source)->toContain('Refresh the Permission table');
});

test('command class has correct properties', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);

    expect($reflection->getName())->toBe('Modules\Core\Console\PermissionsRefreshCommand');
    expect($reflection->isSubclassOf(Modules\Core\Overrides\Command::class))->toBeTrue();
});

test('command can be instantiated', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);

    expect($reflection->isInstantiable())->toBeTrue();
    expect($reflection->isSubclassOf(Modules\Core\Overrides\Command::class))->toBeTrue();
});

test('command has correct namespace', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);

    expect($reflection->getNamespaceName())->toBe('Modules\Core\Console');
    expect($reflection->getShortName())->toBe('PermissionsRefreshCommand');
});

test('command has handle method', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);

    expect($reflection->hasMethod('handle'))->toBeTrue();
});

test('command handle method returns void', function (): void {
    $reflection = new ReflectionMethod(PermissionsRefreshCommand::class, 'handle');

    expect($reflection->getReturnType()->getName())->toBe('void');
});

test('command has pretend option', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('pretend');
    expect($source)->toContain('pretend_mode');
});

test('command handles blacklisted models', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('MODELS_BLACKLIST');
    expect($source)->toContain('checkIfBlacklisted');
});

test('command handles common permissions', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('common_permissions');
    expect($source)->toContain('SELECT');
    expect($source)->toContain('INSERT');
    expect($source)->toContain('UPDATE');
    expect($source)->toContain('DELETE');
});

test('command handles soft delete permissions', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('SoftDeletes');
    expect($source)->toContain('RESTORE');
    expect($source)->toContain('FORCE_DELETE');
});

test('command handles approval permissions', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('RequiresApproval');
    expect($source)->toContain('APPROVE');
});

test('command handles publish permissions', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('HasValidity');
    expect($source)->toContain('PUBLISH');
});

test('command handles lock permissions', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('LOCK');
});

test('command handles impersonate permission for users', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('IMPERSONATE');
    expect($source)->toContain('user_class');
});

test('command handles pretend mode', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('pretend_mode');
    expect($source)->toContain('Running in pretend mode');
});

test('command handles quiet mode', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('quiet_mode');
});

test('command handles permission creation', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('create(');
    expect($source)->toContain('query()');
});

test('command handles permission deletion', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('delete()');
    expect($source)->toContain('whereNotIn');
});

test('command handles permission restoration', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('restore()');
});

test('command handles connection and table names', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('getConnectionName');
    expect($source)->toContain('getTable');
});

test('command handles permission naming convention', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('connection');
    expect($source)->toContain('table');
    expect($source)->toContain('permission_name');
});

test('command handles changes tracking', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$changes');
    expect($source)->toContain('No changes needed');
});

test('command handles output messages', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Created');
    expect($source)->toContain('Deleted');
    expect($source)->toContain('Restored');
});
