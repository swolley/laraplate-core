<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Passkey as BasePasskey;
use Laravel\Passkeys\Passkeys;
use Modules\Core\Actions\Fortify\CreateNewUser;
use Modules\Core\Actions\Fortify\ResetUserPassword;
use Modules\Core\Actions\Fortify\UpdateUserPassword;
use Modules\Core\Actions\Fortify\UpdateUserProfileInformation;
use Modules\Core\Auth\PasskeyLoginAuthorizer;
use Modules\Core\Auth\Providers\FortifyCredentialsProvider;
use Modules\Core\Auth\Providers\SocialiteProvider;
use Modules\Core\Auth\RedirectIfSecondFactorRequired;
use Modules\Core\Auth\Services\AuthenticationService;
use Modules\Core\Models\Passkey;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;
use Override;

final class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        $this->app->instance(LogoutResponse::class, new class() implements LogoutResponse
        {
            /**
             * @param  Request  $request
             */
            public function toResponse($request): mixed // @pest-ignore-type
            {
                // SPA / XHR clients must not receive HTML redirects (axios would
                // follow absolute APP_URL Location headers onto :8000 /admin).
                if ($request->expectsJson()) {
                    return response()->noContent();
                }

                return redirect()->intended(Fortify::redirects('logout', '/admin'));
            }
        });

        $this->app->instance(LoginResponse::class, new class() implements LoginResponse
        {
            /**
             * @param  Request  $request
             */
            public function toResponse($request): mixed // @pest-ignore-type
            {
                if ($request->expectsJson()) {
                    return response()->noContent();
                }

                return redirect()->intended(Fortify::redirects('login'));
            }
        });

        $this->app->instance(RegisterResponse::class, new class() implements RegisterResponse
        {
            public function toResponse($request): mixed // @pest-ignore-type
            {
                if ($request->expectsJson()) {
                    return response()->noContent();
                }

                return redirect()->intended(Fortify::redirects('register'));
            }
        });

        /**
         * @param  Application  $app
         */
        $this->app->singleton(AuthenticationService::class, static fn ($app): AuthenticationService => new AuthenticationService([ // @pest-ignore-type
            $app->make(FortifyCredentialsProvider::class),
            $app->make(SocialiteProvider::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfSecondFactorRequired::class);

        RateLimiter::for('login', static function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', static fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));

        RateLimiter::for('im-still-here', static fn (Request $request) => Limit::perMinute(6)->by($request->session()->get('login.id')));

        $this->registerAuthenticateCallback();
        $this->registerPasskeyLoginGuard();
    }

    /**
     * Enforce an optional module `scope` on login: when the SPA sends a `scope`
     * (its module slug, e.g. `sao`), the credentials must belong to a user who
     * holds at least one permission on that module's entities, otherwise the
     * login is rejected. No scope means an ordinary login.
     */
    private function ensureModuleScope(Request $request, ?Authenticatable $user): void
    {
        $scope = $request->input('scope');

        if (! is_string($scope) || mb_trim($scope) === '') {
            return;
        }

        $authorization = $this->app->make(AuthorizationService::class);

        if ($user instanceof User && $authorization->userHasModuleAccess($user, $scope)) {
            return;
        }

        throw ValidationException::withMessages([
            Fortify::username() => [__('auth.module_scope_denied')],
        ]);
    }

    private function registerAuthenticateCallback(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $service = $this->app->make(AuthenticationService::class);
            $result = $service->authenticate($request);

            if ($result['success']) {
                $this->ensureModuleScope($request, $result['user']);

                $this->rememberLicense($result['license']);

                return $result['user'];
            }

            return null;
        });
    }

    /**
     * A verified passkey proves who the person is, not that the account may log in: the account checks of
     * the password flow run before the session starts. The passkey itself is the second factor, so Fortify's
     * TOTP redirect is not in this path.
     */
    private function registerPasskeyLoginGuard(): void
    {
        Passkeys::usePasskeyModel(Passkey::class);

        Passkeys::authorizeLoginUsing(function (Request $request, PasskeyUser $user, BasePasskey $passkey): bool {
            if (! $user instanceof \App\Models\User || $this->app->make(PasskeyLoginAuthorizer::class)->error($user) !== null) {
                return false;
            }

            $this->ensureModuleScope($request, $user);
            $this->rememberLicense($user->license);

            return true;
        });
    }

    private function rememberLicense(?object $license): void
    {
        if (! config('core.auth.licenses.enabled') || ! $license) {
            return;
        }

        session()->put('license_id', $license->id);

        if (isset($license->uuid)) {
            session()->put('license_uuid', $license->uuid);
        }
    }
}
