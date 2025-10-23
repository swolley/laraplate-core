<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Override;

final class RouteServiceProvider extends ServiceProvider
{
    private string $name = 'Core';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    #[Override]
    public function boot(): void
    {
        parent::boot();

        RateLimiter::for('embeddings', function () {
            return Limit::perMinute(60); // 60 jobs per minute
        });
        RateLimiter::for('indexing', fn () => app()->environment('production') ? [
            // Single worker limit
            Limit::perMinute(300)  // 300 operations per minute (5 per second)
                ->by('indexing.worker'),
            // Global limit for all workers
            Limit::perMinute(1200)  // 1200 operations per minute (20 per second)
                ->by('indexing.global'),
        ] : Limit::perMinute(60));
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    private function getPrefix(): string
    {
        return Str::slug($this->name);
    }

    private function getModuleNamespace(): string
    {
        return str_replace('Providers', 'Http\Controllers', __NAMESPACE__);
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    private function mapWebRoutes(): void
    {
        $name_prefix = $this->getPrefix();
        Route::middleware('web')
            ->namespace($this->namespace)
            ->name($name_prefix . '.')
            ->group([
                module_path($this->name, '/routes/dev.php'),
            ]);

        $route_prefix = 'app';
        Route::middleware(['web'/* , 'verified' */])
            ->namespace($this->namespace)
            ->prefix($route_prefix)
            ->name($name_prefix . '.')
            ->group(module_path($this->name, '/routes/web.php'));

        Route::middleware('auth')
            ->prefix($route_prefix . '/auth')
            ->name($name_prefix . '.')
            ->namespace($this->namespace)
            ->group(module_path($this->name, '/routes/auth.php'));

        Route::middleware('info')
            ->name($name_prefix . '.')
            ->prefix($route_prefix)
            ->namespace($this->namespace)
            ->group(module_path($this->name, '/routes/info.php'));

        // fake reset password for fortify notifications generation. Url can be modified, but name must be 'password.reset' !!
        Route::get($route_prefix . '/auth/reset-password', fn () => abort(Response::HTTP_MOVED_PERMANENTLY))->name('password.reset');
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
    private function mapApiRoutes(): void
    {
        $name_prefix = $this->getPrefix();
        $route_prefix = 'api';
        Route::prefix($route_prefix . '/v1')
            ->middleware([$route_prefix, 'crud_api'])
            ->name(sprintf('%s.%s.', $name_prefix, $route_prefix))
            ->namespace($this->namespace)
            ->group([
                module_path($this->name, '/routes/crud.php'),
                module_path($this->name, '/routes/api.php'),
            ]);
        // }
    }
}
