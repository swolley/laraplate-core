<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

abstract class RouteServiceProvider extends ServiceProvider
{
    protected string $name;

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    protected function getModuleNamespace(): string
    {
        return str_replace('Providers', 'Http\Controllers', __NAMESPACE__);
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->prefix('app')
            ->name($this->getPrefix() . '.')
            ->group([
                module_path($this->name, '/routes/web.php'),
            ]);
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     */
    protected function mapApiRoutes(): void
    {
        $name_prefix = $this->getPrefix();
        $route_prefix = 'api';
        Route::prefix($route_prefix . '/v1')
            ->middleware($route_prefix)
            ->name(sprintf('%s.%s.', $name_prefix, $route_prefix))
            ->group([
                module_path($this->name, '/routes/api.php'),
            ]);
    }

    protected function getPrefix(): string
    {
        return Str::slug($this->name);
    }
}
