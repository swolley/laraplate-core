<?php

declare(strict_types=1);

namespace Modules\Core\Auth\Providers;

use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Modules\Core\Auth\Concerns\ValidatesUserAccount;
use Modules\Core\Auth\Contracts\IAuthenticationProvider;
use Override;

final class SocialiteProvider implements IAuthenticationProvider
{
    use ValidatesUserAccount;
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

            $defaults = [
                'name' => $socialUser->getName(),
                'username' => $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                'social_service' => $request->provider,
                'social_token' => $socialUser->token,
                'social_refresh_token' => $socialUser->refreshToken,
                'social_token_secret' => $socialUser->tokenSecret,
            ];

            if (! User::query()->where('social_id', $socialUser->getId())->exists()) {
                $defaults['password'] = Str::random(40);
            }

            /** @var User $user */
            $user = User::query()->updateOrCreate([
                'social_id' => $socialUser->getId(),
            ], $defaults);

            $error = $this->accountValidityError($user);

            if ($error !== null) {
                return [
                    'success' => false,
                    'user' => null,
                    'error' => $error,
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

}
