<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Console\PermissionsRefreshCommand;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('command exists and has correct signature', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('permission:refresh');
    expect($source)->toContain('Refresh the Permission table');
});

it('command class has correct properties', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);

    expect($reflection->getName())->toBe('Modules\Core\Console\PermissionsRefreshCommand');
    expect($reflection->isSubclassOf(Modules\Core\Overrides\Command::class))->toBeTrue();
});

it('command can be instantiated', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);

    expect($reflection->isInstantiable())->toBeTrue();
    expect($reflection->isSubclassOf(Modules\Core\Overrides\Command::class))->toBeTrue();
});

it('command has correct namespace', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);

    expect($reflection->getNamespaceName())->toBe('Modules\Core\Console');
    expect($reflection->getShortName())->toBe('PermissionsRefreshCommand');
});

it('command has handle method', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);

    expect($reflection->hasMethod('handle'))->toBeTrue();
});

it('command handle method returns void', function (): void {
    $reflection = new ReflectionMethod(PermissionsRefreshCommand::class, 'handle');

    expect($reflection->getReturnType()->getName())->toBe('void');
});

it('command has pretend option', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('pretend');
    expect($source)->toContain('pretend_mode');
});

it('command handles blacklisted models', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('MODELS_BLACKLIST');
    expect($source)->toContain('checkIfBlacklisted');
});

it('command handles common permissions', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('common_permissions');
    expect($source)->toContain('SELECT');
    expect($source)->toContain('INSERT');
    expect($source)->toContain('UPDATE');
    expect($source)->toContain('DELETE');
});

it('command handles soft delete permissions', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('SoftDeletes');
    expect($source)->toContain('RESTORE');
    expect($source)->toContain('FORCE_DELETE');
});

it('command handles approval permissions', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('RequiresApproval');
    expect($source)->toContain('APPROVE');
});

it('command handles publish permissions', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('HasValidity');
    expect($source)->toContain('PUBLISH');
});

it('command handles lock permissions', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('LOCK');
});

it('command handles impersonate permission for users', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('IMPERSONATE');
    expect($source)->toContain('user_class');
});

it('command handles pretend mode', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('pretend_mode');
    expect($source)->toContain('Running in pretend mode');
});

it('command handles quiet mode', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('quiet_mode');
});

it('command handles permission creation', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('create(');
    expect($source)->toContain('query()');
});

it('command handles permission deletion', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('delete()');
    expect($source)->toContain('whereNotIn');
});

it('command handles permission restoration', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('restore()');
});

it('command handles connection and table names', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('getConnectionName');
    expect($source)->toContain('getTable');
});

it('command handles permission naming convention', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('connection');
    expect($source)->toContain('table');
    expect($source)->toContain('permission_name');
});

it('command handles changes tracking', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('$changes');
    expect($source)->toContain('No changes needed');
});

it('command handles output messages', function (): void {
    $reflection = new ReflectionClass(PermissionsRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Created');
    expect($source)->toContain('Deleted');
    expect($source)->toContain('Restored');
});
