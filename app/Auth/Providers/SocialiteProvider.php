<?php

namespace Modules\Core\Auth\Providers;

use Illuminate\Http\Request;
use Modules\Core\Models\User;
use Modules\Core\Models\License;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Auth\Contracts\AuthenticationProviderInterface;

class SocialiteProvider implements AuthenticationProviderInterface
{
    #[\Override]
    public function canHandle(Request $request): bool
    {
        return $request->has('provider') &&
            in_array($request->provider, config('services.socialite.providers', []));
    }

    #[\Override]
    public function authenticate(Request $request): array
    {
        try {
            $socialUser = Socialite::driver($request->provider)->user();

            // check if the user is already registered
            if (
                User::where('email', $socialUser->getEmail())
                ->where(fn(Builder $q) => $q->whereNull('social_id')->orWhere('social_id', '!=', $socialUser->getId()))
                ->exists()
            ) {
                return [
                    'success' => false,
                    'user' => null,
                    'error' => 'User already registered with another account type',
                    'license' => null
                ];
            }

            /** @var User $user */
            $user = User::updateOrCreate([
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
            if ($error = $this->checkLicense($user)) {
                return [
                    'success' => false,
                    'user' => null,
                    'error' => $error,
                    'license' => null
                ];
            }

            return [
                'success' => true,
                'user' => $user,
                'error' => null,
                'license' => $user->license
            ];
        } catch (\Exception) {
            return [
                'success' => false,
                'user' => null,
                'error' => 'Social authentication failed',
                'license' => null
            ];
        }
    }

    #[\Override]
    public function isEnabled(): bool
    {
        return config('auth.providers.socialite.enabled', false);
    }

    #[\Override]
    public function getProviderName(): string
    {
        return 'social';
    }

    private function checkLicense(User $user): ?string
    {
        if (!config('auth.enable_user_licenses')) {
            return null;
        }

        if (!$user->license_id) {
            $available_license = License::query()
                ->doesntHave('user')
                ->first();

            if (
                !$available_license &&
                $user->roles->where('name', 'superadmin')->isEmpty()
            ) {
                return 'No free licenses available';
            }
        }

        return null;
    }
}
