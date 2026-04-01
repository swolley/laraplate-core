<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\DB;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\PasswordPrompt;
use Laravel\Prompts\SearchPrompt;
use Laravel\Prompts\TextPrompt;
use Modules\Core\Console\CreateUserCommand;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class CreateUserPromptFlowStub extends User
{
    protected $table = 'users';

    protected $fillable = ['name', 'username', 'email', 'lang', 'password'];

    /**
     * @return array<string,mixed>
     */
    public function getOperationRules(?string $operation = null): array
    {
        return [
            'name' => ['required', 'string'],
            'username' => ['required', 'string'],
            'email' => ['required', 'email'],
            'lang' => 'in:active,inactive',
            'password' => ['nullable'],
        ];
    }
}

function createUserCommandWithOutput(CreateUserCommand $command): CreateUserCommand
{
    $output = new OutputStyle(new ArrayInput([]), new BufferedOutput());
    $output_reflection = new ReflectionProperty(Illuminate\Console\Command::class, 'output');
    $output_reflection->setValue($command, $output);

    return $command;
}

afterEach(function (): void {
    foreach ([TextPrompt::class, PasswordPrompt::class, SearchPrompt::class, MultiSelectPrompt::class, ConfirmPrompt::class] as $promptClass) {
        $should_fallback = new ReflectionProperty($promptClass, 'shouldFallback');
        $should_fallback->setValue(null, false);

        $fallbacks = new ReflectionProperty($promptClass, 'fallbacks');
        $fallbacks->setValue(null, []);
    }
});

it('covers CreateUserCommand success and failure branches', function (): void {
    config([
        'app.available_locales' => ['en', 'it'],
        'auth.providers.users.model' => User::class,
    ]);

    DB::table('roles')->insert([
        'name' => 'console',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('permissions')->insert([
        'name' => 'console_permission',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $role = Role::query()->where('name', 'console')->firstOrFail();
    $permission = Permission::query()->where('name', 'console_permission')->firstOrFail();

    TextPrompt::fallbackWhen(true);
    TextPrompt::fallbackUsing(static function (): string {
        static $answers = ['console_name', 'console_user', 'console@example.test'];
        static $index = 0;

        return $answers[$index++] ?? 'fallback';
    });
    PasswordPrompt::fallbackWhen(true);
    PasswordPrompt::fallbackUsing(static fn (): string => 'StrongPassword123!');
    SearchPrompt::fallbackWhen(true);
    SearchPrompt::fallbackUsing(static fn (): string => 'it');
    MultiSelectPrompt::fallbackWhen(true);
    MultiSelectPrompt::fallbackUsing(static function (MultiSelectPrompt $prompt) use ($role, $permission): array {
        return $prompt->label === 'Roles' ? [$role->id] : [$permission->id];
    });
    ConfirmPrompt::fallbackWhen(true);
    ConfirmPrompt::fallbackUsing(static function (ConfirmPrompt $prompt): bool {
        return match (true) {
            str_contains($prompt->label, 'custom user permissions') => true,
            str_contains($prompt->label, 'create another user') => false,
            default => false,
        };
    });

    $create_command = createUserCommandWithOutput(new CreateUserCommand());
    expect($create_command->handle())->toBe(0);

    $user = User::query()->where('email', 'console@example.test')->first();
    expect($user)->not->toBeNull()
        ->and($user?->roles->pluck('id')->contains($role->id))->toBeTrue()
        ->and($user?->permissions->pluck('id')->contains($permission->id))->toBeTrue();

    config(['auth.providers.users.model' => stdClass::class]);
    expect($create_command->handle())->toBe(1);
});

it('covers CreateUserCommand in-string options and random password branch', function (): void {
    config(['auth.providers.users.model' => CreateUserPromptFlowStub::class]);

    DB::table('roles')->insert([
        'name' => 'console_second',
        'guard_name' => 'web',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $role = Role::query()->where('name', 'console_second')->firstOrFail();

    TextPrompt::fallbackWhen(true);
    TextPrompt::fallbackUsing(static function (): string {
        static $answers = ['secondary_name', 'secondary_user', 'secondary@example.test'];
        static $index = 0;

        return $answers[$index++] ?? 'secondary_name';
    });
    SearchPrompt::fallbackWhen(true);
    SearchPrompt::fallbackUsing(static fn (): string => 'active');
    PasswordPrompt::fallbackWhen(true);
    PasswordPrompt::fallbackUsing(static fn (): string => '');
    MultiSelectPrompt::fallbackWhen(true);
    MultiSelectPrompt::fallbackUsing(static fn (): array => [$role->id]);
    ConfirmPrompt::fallbackWhen(true);
    ConfirmPrompt::fallbackUsing(static fn (): bool => false);

    $command = createUserCommandWithOutput(new CreateUserCommand());
    expect($command->handle())->toBe(0);

    $created = CreateUserPromptFlowStub::query()->where('name', 'secondary_name')->first();
    expect($created)->not->toBeNull()
        ->and($created?->lang)->toBe('active')
        ->and((string) $created?->password)->not->toBe('');
});
