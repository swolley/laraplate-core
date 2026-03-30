<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Console\InitializeUsers;
use Modules\Core\Models\Role;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\InitializeUsersWithAdminPrompts;
use Modules\Core\Tests\Stubs\InitializeUsersWithoutPrompts;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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

it('creates root and anonymous users when missing using non-interactive test double', function (): void {
    config(['app.name' => 'TestApp']);

    Role::factory()->createMany([
        ['name' => 'superadmin'],
        ['name' => 'admin'],
        ['name' => 'guest'],
    ]);

    $command = new InitializeUsersWithoutPrompts();
    $input = new ArrayInput([]);
    $buffer_output = new BufferedOutput();
    $command->setLaravel(app());
    $command->setInput($input);
    $command->setOutput(new OutputStyle($input, $buffer_output));

    $command->handle();

    /** @var class-string $userClass */
    $userClass = user_class();
    $this->assertDatabaseHas((new $userClass())->getTable(), ['name' => 'root']);
    $this->assertDatabaseHas((new $userClass())->getTable(), ['name' => 'anonymous']);
});

it('creates root, admin and anonymous when missing including optional admin user', function (): void {
    config(['app.name' => 'TestApp']);

    Role::factory()->createMany([
        ['name' => 'superadmin'],
        ['name' => 'admin'],
        ['name' => 'guest'],
    ]);

    $command = new InitializeUsersWithAdminPrompts();
    $input = new ArrayInput([]);
    $buffer_output = new BufferedOutput();
    $command->setLaravel(app());
    $command->setInput($input);
    $command->setOutput(new OutputStyle($input, $buffer_output));

    $command->handle();

    /** @var class-string $userClass */
    $userClass = user_class();
    $table = (new $userClass())->getTable();
    $this->assertDatabaseHas($table, ['name' => 'root', 'email' => 'root@example.com']);
    $this->assertDatabaseHas($table, ['name' => 'admin', 'email' => 'admin@example.com']);
    $this->assertDatabaseHas($table, ['name' => 'anonymous']);
});

it('creates users via artisan when database is empty using console fallback prompts and skips admin', function (): void {
    config(['app.name' => 'TestApp']);

    Role::factory()->createMany([
        ['name' => 'superadmin'],
        ['name' => 'admin'],
        ['name' => 'guest'],
    ]);

    registerInitializeUsersCommand();

    /** @var class-string $userClass */
    $userClass = user_class();

    $this->artisan('auth:initialize-users')
        ->expectsQuestion('Please specify a root user email', 'root@example.com')
        ->expectsQuestion('Please specify a root user password', 'rootpass123')
        ->expectsQuestion('Please confirm the password', 'rootpass123')
        ->expectsQuestion('Please specify a admin user email or leave blank to skip', '')
        ->assertExitCode(BaseCommand::SUCCESS);

    $table = (new $userClass())->getTable();
    $this->assertDatabaseHas($table, ['name' => 'root', 'email' => 'root@example.com']);
    $this->assertDatabaseMissing($table, ['name' => 'admin']);
    $this->assertDatabaseHas($table, ['name' => 'anonymous']);
});

it('creates users via artisan when database is empty including admin via console fallback prompts', function (): void {
    config(['app.name' => 'TestApp']);

    Role::factory()->createMany([
        ['name' => 'superadmin'],
        ['name' => 'admin'],
        ['name' => 'guest'],
    ]);

    registerInitializeUsersCommand();

    /** @var class-string $userClass */
    $userClass = user_class();

    $this->artisan('auth:initialize-users')
        ->expectsQuestion('Please specify a root user email', 'root@example.com')
        ->expectsQuestion('Please specify a root user password', 'rootpass123')
        ->expectsQuestion('Please confirm the password', 'rootpass123')
        ->expectsQuestion('Please specify a admin user email or leave blank to skip', 'admin@example.com')
        ->expectsQuestion('Please specify a admin user password', 'adminpass123')
        ->expectsQuestion('Please confirm the password', 'adminpass123')
        ->assertExitCode(BaseCommand::SUCCESS);

    $table = (new $userClass())->getTable();
    $this->assertDatabaseHas($table, ['name' => 'root', 'email' => 'root@example.com']);
    $this->assertDatabaseHas($table, ['name' => 'admin', 'email' => 'admin@example.com']);
    $this->assertDatabaseHas($table, ['name' => 'anonymous']);
});
