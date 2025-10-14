<?php

declare(strict_types=1);

namespace Modules\Core\Auth\Providers;

use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Modules\Core\Auth\Contracts\IAuthenticationProvider;
use Modules\Core\Models\License;
use Modules\Core\Models\User;
use Override;

final class SocialiteProvider implements IAuthenticationProvider
{
    #[Override]
    public function canHandle(Request $request): bool
    {
        return $request->has('provider')
            && in_array($request->provider, config('services.socialite.providers', []), true);
    }

    #[Override]
    public function authenticate(Request $request): array
    {
        try {
            $socialUser = Socialite::driver($request->provider)->user();

            // check if the user is already registered
            if (
                User::query()->where('email', $socialUser->getEmail())
                    ->where(fn (Builder $q) => $q->whereNull('social_id')->orWhere('social_id', '!=', $socialUser->getId()))
                    ->exists()
            ) {
                return [
                    'success' => false,
                    'user' => null,
                    'error' => 'User already registered with another account type',
                    'license' => null,
                ];
            }

            /** @var User $user */
            $user = User::query()->updateOrCreate([
                'social_id' => $socialUser->getId(),
            ], [
                'name' => $socialUser->getName(),
                'username' => $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                'social_service' => $request->provider,
                'social_token' => $socialUser->token,
                'social_refresh_token' => $socialUser->refreshToken,
                'social_token_secret' => $socialUser->tokenSecret,
            ]);

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
        } catch (Exception) {
            return [
                'success' => false,
                'user' => null,
                'error' => 'Social authentication failed',
                'license' => null,
            ];
        }
    }

    #[Override]
    public function isEnabled(): bool
    {
        return config('auth.providers.socialite.enabled', false);
    }

    #[Override]
    public function getProviderName(): string
    {
        return 'social';
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
