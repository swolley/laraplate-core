<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Override;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Modules\Core\Actions\Fortify\CreateNewUser;
use Modules\Core\Auth\Providers\SocialiteProvider;
use Modules\Core\Actions\Fortify\ResetUserPassword;
use Modules\Core\Actions\Fortify\UpdateUserPassword;
use Modules\Core\Auth\Services\AuthenticationService;
use Modules\Core\Auth\Providers\FortifyCredentialsProvider;
use Modules\Core\Actions\Fortify\UpdateUserProfileInformation;

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
            public function toResponse($request)
            {
                if ($request->wantsJson()) {
                    return redirect()->route('core.auth.userInfo');
                }

                // If we are in a Filament context, redirect to Filament login
                if (str_contains($request->path(), 'admin')) {
                    return redirect()->route('filament.auth.login');
                }

                return redirect()->intended(Fortify::redirects('logout', '/'));
            }
        });

        $this->app->instance(LoginResponse::class, new class() implements LoginResponse
        {
            public function toResponse($request)
            {
                if ($request->wantsJson()) {
                    return redirect()->route('core.auth.userInfo');
                }

                // If we are in a Filament context, redirect to Filament dashboard
                if (str_contains($request->path(), 'admin')) {
                    return redirect()->route('filament.pages.dashboard');
                }

                return redirect()->intended(Fortify::redirects('login'));
            }
        });

        $this->app->instance(RegisterResponse::class, new class() implements RegisterResponse
        {
            public function toResponse($request)
            {
                if ($request->wantsJson()) {
                    return redirect()->route('core.auth.userInfo');
                }

                // If we are in a Filament context, redirect to Filament dashboard
                if (str_contains($request->path(), 'admin')) {
                    return redirect()->route('filament.pages.dashboard');
                }

                return redirect()->intended(Fortify::redirects('register'));
            }
        });

        $this->app->singleton(AuthenticationService::class, fn ($app) => new AuthenticationService([
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

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));

        RateLimiter::for('im-still-here', fn (Request $request) => Limit::perMinute(6)->by($request->session()->get('login.id')));

        Fortify::authenticateUsing(function ($request) {
            $service = $this->app->make(AuthenticationService::class);
            $result = $service->authenticate($request);

            if ($result['success']) {
                if (config('auth.enable_user_licenses') && $result['license']) {
                    session()->put('license_id', $result['license']->id);
                }

                return $result['user'];
            }

            return null;
        });
    }
}
