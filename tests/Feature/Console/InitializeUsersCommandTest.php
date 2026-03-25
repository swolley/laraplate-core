<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Console\InitializeUsers;
use Modules\Core\Models\Role;
use Modules\Core\Tests\LaravelTestCase;
use Symfony\Component\Console\Command\Command as BaseCommand;

uses(LaravelTestCase::class, RefreshDatabase::class);

function registerInitializeUsersCommand(): void
{
    /** @var Illuminate\Contracts\Console\Kernel $kernel */
    $kernel = app(Illuminate\Contracts\Console\Kernel::class);

    foreach ($kernel->all() as $name => $command) {
        if ($name === 'auth:initialize-users') {
            return;
        }
    }

    $kernel->registerCommand(app(InitializeUsers::class));
}

it('creates root, admin and anonymous users when they do not exist', function (): void {
    /** @var class-string $userClass */
    $userClass = user_class();

    // Ensure required roles exist
    $roles = Role::factory()->createMany([
        ['name' => 'superadmin'],
        ['name' => 'admin'],
        ['name' => 'guest'],
    ])->keyBy('name');

    // Pre-create users so the command only runs "already exists" path (no interactive prompts)
    $root = $userClass::factory()->create([
        'name' => 'root',
        'username' => 'root',
        'email' => 'root@example.com',
    ]);
    $root->assignRole($roles['superadmin']);

    $admin = $userClass::factory()->create([
        'name' => 'admin',
        'username' => 'admin',
        'email' => 'admin@example.com',
    ]);
    $admin->assignRole($roles['admin']);

    $anonymous = $userClass::factory()->create([
        'name' => 'anonymous',
        'username' => 'anonymous',
        'email' => 'anonymous@example.com',
    ]);
    $anonymous->assignRole($roles['guest']);

    registerInitializeUsersCommand();

    $tester = $this->artisan('auth:initialize-users');

    $tester->assertExitCode(BaseCommand::SUCCESS);

    $this->assertDatabaseHas((new $userClass())->getTable(), ['name' => 'root']);
    $this->assertDatabaseHas((new $userClass())->getTable(), ['name' => 'anonymous']);
});
