<?php

declare(strict_types=1);

namespace Modules\Core\Auth\Providers;

use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Auth\Contracts\IAuthenticationProvider;
use Modules\Core\Models\License;
use Modules\Core\Models\User;
use Override;

final class FortifyCredentialsProvider implements IAuthenticationProvider
{
    #[Override]
    public function canHandle(Request $request): bool
    {
        if ($request->has(['email', 'password'])) {
            return true;
        }

        return $request->has(['username', 'password']);
    }

    #[Override]
    public function authenticate(Request $request): array
    {
        $username = $request->get('username');
        $email = $request->get('email');
        $password = $request->get('password');

        $query = User::query()->has('roles');

        if ($username) {
            $query->where('username', $username);
        } else {
            $query->where('email', $email);
        }

        $user = $query->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return [
                'success' => false,
                'user' => null,
                'error' => 'Invalid credentials',
                'license' => null,
            ];
        }

        // Verifica email
        if ($this->shouldVerifyEmail($user)) {
            return [
                'success' => false,
                'user' => null,
                'error' => 'Email not verified',
                'license' => null,
            ];
        }

        // Verifica licenza
        $error = $this->checkLicense($user);

        if ($error !== null && $error !== '' && $error !== '0') {
            return [
                'success' => false,
                'user' => null,
                'error' => $error,
                'license' => null,
            ];
        }

        return [
            'success' => true,
            'user' => $user,
            'error' => null,
            'license' => $user->license,
        ];
    }

    #[Override]
    public function isEnabled(): bool
    {
        return config('auth.providers.users.driver') === 'eloquent';
    }

    #[Override]
    public function getProviderName(): string
    {
        return 'credentials';
    }

    private function shouldVerifyEmail(User $user): bool
    {
        return class_uses_trait($user, MustVerifyEmail::class)
            && ! $user->hasVerifiedEmail();
    }

    private function checkLicense(User $user): ?string
    {
        if (! config('auth.enable_user_licenses')) {
            return null;
        }

        if (! $user->license_id) {
            $available_license = License::query()
                ->doesntHave('user')
                ->first();

            if (
                ! $available_license
                && $user->roles->where('name', 'superadmin')->isEmpty()
            ) {
                return 'No free licenses available';
            }
        }

        return null;
    }
}
