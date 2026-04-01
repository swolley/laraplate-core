<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Models\Role;
use Modules\Core\Overrides\Command;
use Override;

class InitializeUsers extends Command
{
    #[Override]
    protected $signature = 'auth:initialize-users';

    #[Override]
    protected $description = 'Initialize users. <fg=yellow>(⚡ Modules\Core)</fg=yellow>';

    public function handle(): void
    {
        /** @var class-string<\Modules\Core\Models\User> $user_class */
        $user_class = user_class();

        $root = 'root';
        $admin = 'admin';
        $anonymous = 'anonymous';

        $groups = Role::all()->keyBy('name');

        DB::transaction(function () use ($admin, $root, $anonymous, $user_class, $groups): void {
            if (! $user_class::query()->where('name', $root)->exists()) {
                $credentials = $this->promptRootUserCredentials();
                $email = $credentials['email'];
                $password = $credentials['password'];

                /** @var \Modules\Core\Models\User $root_user */
                $root_user = $user_class::query()->make([
                    'name' => $root,
                    'username' => $root,
                    'email' => $email,
                    'password' => $password,
                ]);
                $root_user->forceFill([
                    'email_verified_at' => now(),
                    'locked_at' => now(),
                ]);
                $root_user->setSkipValidation(true);
                $root_user->save();
                $root_user->assignRole($groups['superadmin']);
                $this->info($root . ' created');
            } else {
                $this->info($root . ' already exists');
            }

            if (! $user_class::query()->where('name', $admin)->exists()) {
                $admin_credentials = $this->promptOptionalAdminCredentials();

                if ($admin_credentials !== null) {
                    $email = $admin_credentials['email'];
                    $password = $admin_credentials['password'];

                    /** @var \Modules\Core\Models\User $admin_user */
                    $admin_user = $user_class::query()->make([
                        'name' => $admin,
                        'username' => $admin,
                        'email' => $email,
                        'password' => $password,
                    ]);
                    $admin_user->forceFill([
                        'email_verified_at' => now(),
                    ]);
                    $admin_user->setSkipValidation(true);
                    $admin_user->save();
                    $admin_user->assignRole($groups[$admin]);
                    $this->info($admin . ' created');
                }
            } else {
                $this->info($admin . ' already exists');
            }

            if (! $user_class::query()->where('name', $anonymous)->exists()) {
                /** @var \Modules\Core\Models\User $anonymous_user */
                $anonymous_user = $user_class::query()->make([
                    'name' => $anonymous,
                    'username' => $anonymous,
                    'email' => $anonymous . '@' . str_replace('_', '', Str::slug(config('app.name'))) . '.com',
                    'password' => Str::random(16),
                ]);
                $anonymous_user->forceFill([
                    'email_verified_at' => now(),
                ]);
                $anonymous_user->setSkipValidation(true);
                $anonymous_user->save();
                $anonymous_user->assignRole($groups['guest']);
                $this->info($anonymous . ' created');
            } else {
                $this->info($anonymous . ' already exists');
            }
        });
    }

    /**
     * @return array{email: string, password: string}
     */
    protected function promptRootUserCredentials(): array
    {
        $root = 'root';
        $email = text(sprintf('Please specify a %s user email', $root), required: true, validate: static fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Please type a valid email');
        $password = password(sprintf('Please specify a %s user password', $root), required: true);
        password('Please confirm the password', required: true, validate: fn (string $value): ?string => $password !== $value ? "Passwords don't match" : null);

        return ['email' => $email, 'password' => $password];
    }

    /**
     * @return array{email: string, password: string}|null
     */
    protected function promptOptionalAdminCredentials(): ?array
    {
        $admin = 'admin';
        $email = text(
            sprintf('Please specify a %s user email or leave blank to skip', $admin),
            required: false,
            validate: static fn (string $value): ?string => match (true) {
                $value === '' || $value === '0' => null,
                default => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Please type a valid email',
            },
        );

        if ($email === '' || $email === '0') {
            return null;
        }

        $password = password(sprintf('Please specify a %s user password', $admin), required: true);
        password('Please confirm the password', required: true, validate: fn (string $value): ?string => $password !== $value ? "Passwords don't match" : null);

        return ['email' => $email, 'password' => $password];
    }
}
