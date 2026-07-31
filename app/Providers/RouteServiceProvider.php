<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Modules\Core\Overrides\RouteServiceProvider as ServiceProvider;
use Override;

final class RouteServiceProvider extends ServiceProvider
{
    #[Override]
    protected string $name = 'Core';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    #[Override]
    public function boot(): void
    {
        parent::boot();

        // Rate limiters for Core module queues
        RateLimiter::for('versions', static function () {
            return Limit::perMinute(120); // 120 version jobs per minute (2 per second)
        });

        RateLimiter::for('translations', static function (object $job): Limit {
            return Limit::perMinute(30); // 30 job al minuto
        });

        RateLimiter::for('embeddings', static function () {
            return Limit::perMinute(10); // 10 embedding jobs per minute (0.16 per second)
        });

        RateLimiter::for('indexing', static fn () => app()->environment('production') ? [
            // Single worker limit
            Limit::perMinute(300)  // 300 operations per minute (5 per second)
                ->by('indexing.worker'),
            // Global limit for all workers
            Limit::perMinute(1200)  // 1200 operations per minute (20 per second)
                ->by('indexing.global'),
        ] : Limit::perMinute(60));
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    #[Override]
    protected function mapWebRoutes(): void
    {
        // Registered eagerly on purpose: dev.php declares `swagger/{filename}`, the same
        // URI+method the wotz/laravel-swagger-ui package declares. On a URI collision the
        // RouteCollection keeps the LAST route added, and the package's one must win — it
        // carries the `EnsureUserIsAuthorized` middleware, and CoreServiceProvider binds
        // OpenApiJsonController to DocsController so the merged spec is served from it.
        Route::middleware('web')
            ->namespace($this->namespace)
            ->name($this->getPrefix() . '.')
            ->group([
                module_path($this->name, '/routes/dev.php'),
            ]);

        // Core boots first (module.json priority 0) so other modules can build on its bindings,
        // but Laravel matches routes in registration order with no notion of specificity. The
        // remaining Core routes therefore register in `booted`, after every module provider:
        // otherwise the generic CRUD catch-all (`app/crud/{verb}/{module}/{entity}`) would
        // shadow module-specific routes of the same shape, e.g. `app/crud/select/ai/conversations`.
        $this->app->booted(function (): void {
            $this->registerDeferredWebRoutes();
        });
    }

    private function registerDeferredWebRoutes(): void
    {
        $name_prefix = $this->getPrefix();
        $route_prefix = 'app';

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
        Route::get($route_prefix . '/auth/reset-password', static fn () => abort(Response::HTTP_MOVED_PERMANENTLY))->name('password.reset');

        // Loads routes/web.php, which holds the generic CRUD catch-all. Kept last so the more
        // specific Core routes above win the match.
        parent::mapWebRoutes();
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
    #[Override]
    protected function mapApiRoutes(): void
    {
        // Deferred for the same reason as mapWebRoutes().
        $this->app->booted(function (): void {
            $this->registerApiRoutes();
        });
    }

    private function registerApiRoutes(): void
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
    }
}
