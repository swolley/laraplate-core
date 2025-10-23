<?php

declare(strict_types=1);

use Modules\Core\Models\License;
use Modules\Core\Models\User;

test('user creation command workflow structure', function (): void {
    // 1. Test user creation command structure
    $reflection = new ReflectionClass(Modules\Core\Console\CreateUserCommand::class);
    expect($reflection->hasMethod('handle'))->toBeTrue();

    // 2. Test command signature
    $source = file_get_contents($reflection->getFileName());
    expect($source)->toContain('auth:create-user');
    expect($source)->toContain('Create new user');

    // 3. Test command uses Laravel Prompts
    expect($source)->toContain('Laravel\\Prompts\\confirm');
    expect($source)->toContain('Laravel\\Prompts\\multiselect');
    expect($source)->toContain('Laravel\\Prompts\\password');
    expect($source)->toContain('Laravel\\Prompts\\text');

    // 4. Test command uses database transactions
    expect($source)->toContain('$this->db->transaction');

    // 5. Test command creates users with roles and permissions
    expect($source)->toContain('$user->roles()->sync');
    expect($source)->toContain('$user->permissions()->sync');
});

test('license management command workflow structure', function (): void {
    // 1. Test license management command structure
    $reflection = new ReflectionClass(Modules\Core\Console\HandleLicensesCommand::class);
    expect($reflection->hasMethod('handle'))->toBeTrue();

    // 2. Test command signature
    $source = file_get_contents($reflection->getFileName());
    expect($source)->toContain('auth:licenses');
    expect($source)->toContain('Renew, add or delete user licenses');

    // 3. Test command uses Laravel Prompts
    expect($source)->toContain('Laravel\\Prompts\\confirm');
    expect($source)->toContain('Laravel\\Prompts\\select');
    expect($source)->toContain('Laravel\\Prompts\\table');
    expect($source)->toContain('Laravel\\Prompts\\text');

    // 4. Test command uses database transactions
    expect($source)->toContain('$this->db->transaction');

    // 5. Test command has license management methods
    expect($source)->toContain('renewLicenses');
    expect($source)->toContain('addLicenses');
    expect($source)->toContain('closeLicenses');
    expect($source)->toContain('listLicenses');
});

test('permissions refresh command workflow structure', function (): void {
    // 1. Test permissions refresh command structure
    $reflection = new ReflectionClass(Modules\Core\Console\PermissionsRefreshCommand::class);
    expect($reflection->hasMethod('handle'))->toBeTrue();

    // 2. Test command signature
    $source = file_get_contents($reflection->getFileName());
    expect($source)->toContain('permission:refresh');
    expect($source)->toContain('Refresh the Permission table');

    // 3. Test command has pretend option
    expect($source)->toContain('pretend');
    expect($source)->toContain('pretend_mode');

    // 4. Test command uses database transactions
    expect($source)->toContain('$this->db->beginTransaction');
    expect($source)->toContain('$this->db->commit');
    expect($source)->toContain('$this->db->rollBack');

    // 5. Test command handles permission creation
    expect($source)->toContain('create(');
    expect($source)->toContain('query()');
});

test('entity creation command workflow structure', function (): void {
    // 1. Test entity creation command structure
    $reflection = new ReflectionClass(Modules\Cms\Console\CreateEntityCommand::class);
    expect($reflection->hasMethod('handle'))->toBeTrue();

    // 2. Test command signature
    $source = file_get_contents($reflection->getFileName());
    expect($source)->toContain('model:create-entity');
    expect($source)->toContain('Create new cms entity');

    // 3. Test command uses Laravel Prompts
    expect($source)->toContain('Laravel\\Prompts\\confirm');
    expect($source)->toContain('Laravel\\Prompts\\multiselect');
    expect($source)->toContain('Laravel\\Prompts\\select');
    expect($source)->toContain('Laravel\\Prompts\\text');

    // 4. Test command uses database transactions
    expect($source)->toContain('$this->db->transaction');

    // 5. Test command creates entity with fillable attributes
    expect($source)->toContain('getFillable');
    expect($source)->toContain('getOperationRules');
});

test('content model creation command workflow structure', function (): void {
    // 1. Test content model creation command structure
    $reflection = new ReflectionClass(Modules\Cms\Console\CreateContentModelCommand::class);
    expect($reflection->hasMethod('handle'))->toBeTrue();

    // 2. Test command signature
    $source = file_get_contents($reflection->getFileName());
    expect($source)->toContain('model:make-content-model');
    expect($source)->toContain('Create a new content model');

    // 3. Test command requires entity argument
    expect($source)->toContain('entity');
    expect($source)->toContain('InputArgument::REQUIRED');

    // 4. Test command implements PromptsForMissingInput
    expect($reflection->implementsInterface(Illuminate\Contracts\Console\PromptsForMissingInput::class))->toBeTrue();

    // 5. Test command handles entity name conversion
    expect($source)->toContain('Str::studly');
});

test('database seeder workflow structure', function (): void {
    // 1. Test core seeder structure
    $reflection = new ReflectionClass(Modules\Core\Database\Seeders\CoreDatabaseSeeder::class);
    expect($reflection->hasMethod('run'))->toBeTrue();

    // 2. Test CMS seeder structure
    $reflection = new ReflectionClass(Modules\Cms\Database\Seeders\CmsDatabaseSeeder::class);
    expect($reflection->hasMethod('run'))->toBeTrue();

    // 3. Test seeder calls permission refresh
    $source = file_get_contents($reflection->getFileName());
    expect($source)->toContain('Artisan::call(\'permission:refresh\')');

    // 4. Test seeder populates basic data
    expect($source)->toContain('Field::factory()');
    expect($source)->toContain('Entity::factory()');
});

test('migration workflow structure', function (): void {
    // 1. Test migration files exist
    $migrationFiles = glob(module_path('Core', 'database/migrations/*.php'));
    expect($migrationFiles)->not->toBeEmpty();

    $cmsMigrationFiles = glob(module_path('Cms', 'database/migrations/*.php'));
    expect($cmsMigrationFiles)->not->toBeEmpty();

    // 2. Test migration files have correct structure
    foreach ($migrationFiles as $file) {
        $source = file_get_contents($file);
        expect($source)->toContain('use Illuminate\\Database\\Migrations\\Migration');
        expect($source)->toContain('use Illuminate\\Database\\Schema\\Blueprint');
        expect($source)->toContain('use Illuminate\\Support\\Facades\\Schema');
    }

    // 3. Test CMS migration files have correct structure
    foreach ($cmsMigrationFiles as $file) {
        $source = file_get_contents($file);
        expect($source)->toContain('use Illuminate\\Database\\Migrations\\Migration');
        expect($source)->toContain('use Illuminate\\Database\\Schema\\Blueprint');
        expect($source)->toContain('use Illuminate\\Support\\Facades\\Schema');
    }
});

test('cache clearing workflow structure', function (): void {
    // 1. Test cache clear command exists
    $reflection = new ReflectionClass(Illuminate\Cache\Console\CacheClearCommand::class);
    expect($reflection->hasMethod('handle'))->toBeTrue();

    // 2. Test config clear command exists
    $reflection = new ReflectionClass(Illuminate\Foundation\Console\ConfigClearCommand::class);
    expect($reflection->hasMethod('handle'))->toBeTrue();

    // 3. Test route clear command exists
    $reflection = new ReflectionClass(Illuminate\Foundation\Console\RouteClearCommand::class);
    expect($reflection->hasMethod('handle'))->toBeTrue();

    // 4. Test view clear command exists
    $reflection = new ReflectionClass(Illuminate\Foundation\Console\ViewClearCommand::class);
    expect($reflection->hasMethod('handle'))->toBeTrue();
});
