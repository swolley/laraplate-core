<?php

declare(strict_types=1);

use Modules\Core\Casts\ActionEnum;
use Modules\Core\Console\PermissionsRefreshCommand;
use Modules\Core\Helpers\HelpersCache;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\Core\Models\Version;
use Modules\Core\Tests\Fixtures\PermissionsRefreshPlainModel;
use Symfony\Component\Console\Application as SymfonyConsoleApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

afterEach(function (): void {
    HelpersCache::clearModels();
});

/**
 * @param  array<string, bool|string>  $options
 */
function runPermissionsRefreshForCoverage(array $options = []): string
{
    $command = app(PermissionsRefreshCommand::class);
    $command->setLaravel(app());
    $command->setApplication(new SymfonyConsoleApplication('coverage', '1.0.0'));
    $command->mergeApplicationDefinition();
    $input = new ArrayInput(
        array_merge(['command' => $command->getName()], $options),
        $command->getDefinition(),
    );
    $output = new BufferedOutput();
    $command->run($input, $output);

    return $output->fetch();
}

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

it('runs in pretend mode with merged quiet option', function (): void {
    HelpersCache::clearModels();
    HelpersCache::setModels('active', [User::class, Version::class]);

    $command = app(PermissionsRefreshCommand::class);
    $command->setLaravel(app());
    $command->setApplication(new SymfonyConsoleApplication('coverage', '1.0.0'));
    $command->mergeApplicationDefinition();

    $input = new ArrayInput([
        'command' => $command->getName(),
        '--pretend' => true,
        '--quiet' => true,
    ], $command->getDefinition());

    $exit = $command->run($input, new BufferedOutput());
    expect($exit)->toBe(0);

    HelpersCache::clearModels();
});

it('detects blacklisted models via checkIfBlacklisted helper', function (): void {
    $command = app(PermissionsRefreshCommand::class);
    $command->setLaravel(app());
    $method = new ReflectionMethod(PermissionsRefreshCommand::class, 'checkIfBlacklisted');
    $method->setAccessible(true);

    expect($method->invoke($command, Version::class))->toBeTrue()
        ->and($method->invoke($command, User::class))->toBeFalse();
});

it('runs permission refresh in pretend mode with output enabled', function (): void {
    HelpersCache::clearModels();
    HelpersCache::setModels('active', [User::class]);

    $command = app(PermissionsRefreshCommand::class);
    $command->setLaravel(app());
    $command->setApplication(new SymfonyConsoleApplication('coverage', '1.0.0'));
    $command->mergeApplicationDefinition();

    $output = new BufferedOutput();
    $input = new ArrayInput([
        'command' => $command->getName(),
        '--pretend' => true,
    ], $command->getDefinition());

    $exit = $command->run($input, $output);
    expect($exit)->toBe(0)
        ->and($output->fetch())->not->toBe('');

    HelpersCache::clearModels();
});

it('skips non-string and missing model entries and prints bypass for blacklisted classes', function (): void {
    HelpersCache::setModels('active', [
        404,
        'Definitely\\Not\\A\\LoadedModelClass',
        Version::class,
        PermissionsRefreshPlainModel::class,
    ]);

    $output = runPermissionsRefreshForCoverage([]);

    expect($output)->toContain(sprintf("Bypassing '%s' class", Version::class));
});

it('removes delete approve and publish permissions when the model does not support those features', function (): void {
    $delete_name = 'default.perm_refresh_plain.delete';
    $approve_name = 'default.perm_refresh_plain.approve';
    $publish_name = 'default.perm_refresh_plain.publish';

    Permission::query()->whereIn('name', [$delete_name, $approve_name, $publish_name])->delete();

    Permission::create(['name' => $delete_name, 'guard_name' => 'web']);
    Permission::create(['name' => $approve_name, 'guard_name' => 'web']);
    Permission::create(['name' => $publish_name, 'guard_name' => 'web']);

    HelpersCache::setModels('active', [PermissionsRefreshPlainModel::class]);

    $output = runPermissionsRefreshForCoverage([]);

    expect(Permission::query()->where('name', $delete_name)->count())->toBe(0);
    expect(Permission::query()->where('name', $approve_name)->count())->toBe(0);
    expect(Permission::query()->where('name', $publish_name)->count())->toBe(0);
    expect($output)->toContain("Deleted '{$delete_name}' permission");
    expect($output)->toContain("Deleted '{$approve_name}' permission");
});

it('drops permissions that no longer match any inspected model', function (): void {
    $orphan_name = 'default.zz_' . bin2hex(random_bytes(4)) . '.select';

    Permission::create(['name' => $orphan_name, 'guard_name' => 'web']);

    HelpersCache::setModels('active', [PermissionsRefreshPlainModel::class]);

    $output = runPermissionsRefreshForCoverage([]);

    expect(Permission::query()->where('name', $orphan_name)->count())->toBe(0);
    expect($output)->toContain("Deleted '{$orphan_name}' permission");
});

it('reports no changes when a second run finds the permission set already in sync', function (): void {
    HelpersCache::setModels('active', [User::class]);

    runPermissionsRefreshForCoverage([]);
    HelpersCache::setModels('active', [User::class]);

    $output = runPermissionsRefreshForCoverage([]);

    expect($output)->toContain('No changes needed');
});

it('creates impersonate permission for the configured user model when missing', function (): void {
    $user_class = user_class();
    $reflection = new ReflectionClass($user_class);
    $instance = $reflection->newInstanceWithoutConstructor();
    $connection = $instance->getConnectionName() ?? 'default';
    $table = $instance->getTable();
    $impersonate_name = sprintf('%s.%s.%s', $connection, $table, ActionEnum::IMPERSONATE->value);

    Permission::query()->where('name', $impersonate_name)->delete();

    HelpersCache::setModels('active', [User::class]);

    $output = runPermissionsRefreshForCoverage([]);

    expect(Permission::query()->where('name', $impersonate_name)->exists())->toBeTrue();
    expect($output)->toContain($impersonate_name);
    expect($output)->toContain("Created '{$impersonate_name}' permission");
});
