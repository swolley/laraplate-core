<?php

declare(strict_types=1);

namespace Modules\Core\Auth\Providers;

use App\Models\User;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Auth\Concerns\ValidatesUserAccount;
use Modules\Core\Auth\Contracts\IAuthenticationProvider;
use Override;

final class FortifyCredentialsProvider implements IAuthenticationProvider
{
    use ValidatesUserAccount;

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
                'error' => 'Invalid credentials or user not allowed to login',
                'license' => null,
            ];
        }

        $error = $this->accountValidityError($user);

        if ($error !== null) {
            return [
                'success' => false,
                'user' => null,
                'error' => $error,
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

        if (! in_array($error, [null, '', '0'], true)) {
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
}
