<?php

namespace Modules\Core\Providers;

use Illuminate\Support\Str;
use Modules\Core\Locking\Locked;
use Modules\Core\Models\CronJob;
use Illuminate\Support\Facades\DB;
use Modules\Core\Cache\Repository;
use Illuminate\Support\Facades\URL;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Helpers\SoftDeletes;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Password;
use Nwidart\Modules\Traits\PathNamespace;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Core\Overrides\ServiceProvider;
use Modules\Core\Locking\LockedModelSubscriber;
use Spatie\Permission\Middleware\RoleMiddleware;
use Illuminate\Cache\Repository as BaseRepository;
use Modules\Core\Http\Middleware\PreviewMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Modules\Core\Http\Middleware\ConvertStringToBoolean;
use Modules\Core\Http\Middleware\LocalizationMiddleware;
use Illuminate\Contracts\Cache\Repository as BaseContract;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;

/**
 * @property \Illuminate\Foundation\Application $app
 */
class CoreServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Core';

    protected string $nameLower = 'core';

    protected $subscribe = [
        LockedModelSubscriber::class,
    ];

    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        $this->registerAuths();
        $this->registerMiddlewares();
        $this->registerModels();

        if ($this->app->isProduction() && config('core.force_https')) {
            URL::forceScheme('https');
        }

        Password::defaults(fn() => Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised());
    }

    /**
     * Register the service provider.
     */
    #[\Override]
    public function register(): void
    {
        $this->registerConfig();
        $this->registerCache();

        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton(Locked::class, fn() => new Locked());
        $this->app->alias(Locked::class, 'locked');

        $this->app->alias(BaseSoftDeletes::class, SoftDeletes::class);
        // $app->alias('App\\Models\\User', user_class());

        if ($this->app->isLocal()) {
            $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
        }
    }

    public function registerAuths(): void
    {
        // bypass all other checks if user is super admin
        Gate::before(fn(?User $user) => $user && $user instanceof \Modules\Core\Models\User && $user->isSuperAdmin() ? true : null);
    }

    protected function registerModels(): void
    {
        Model::preventSilentlyDiscardingAttributes(!$this->app->isProduction());
        Model::shouldBeStrict();
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        $module_commands_subpath = config('modules.paths.generator.command.path');
        $commands = $this->inspectFolderCommands($module_commands_subpath);

        $locking_commands_subpath = Str::replace('Console', 'Locking/Console', $module_commands_subpath);
        $locking_commands = $this->inspectFolderCommands($locking_commands_subpath);
        array_push($commands, ...$locking_commands);

        $search_commands_subpath = Str::replace('Console', 'Search/Console', $module_commands_subpath);
        $search_commands = $this->inspectFolderCommands($search_commands_subpath);
        array_push($commands, ...$search_commands);

        $this->commands($commands);

        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);
            $crons = [];
            $cache_key = new CronJob()->getTable();
            if (Cache::has($cache_key)) {
                $crons = Cache::get($cache_key);
            } else {
                try {
                    if (Schema::hasTable($cache_key)) {
                        $crons = CronJob::query()->where('is_active', true)->select(['command', 'schedule'])->get()->toArray();
                        Cache::put($cache_key, $crons);
                    }
                } catch (\Exception $e) {
                    report($e);
                }
            }

            foreach ($crons as $cron) {
                $schedule->command($cron['command'])->cron($cron['schedule'])->onOneServer();
            }
        });
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

        $componentNamespace = $this->module_namespace($this->name, $this->app_path(config('modules.paths.generator.component-class.path')));
        Blade::componentNamespace($componentNamespace, $this->nameLower);
    }

    protected function registerMiddlewares()
    {
        $router = app('router');
        $router->middleware(LocalizationMiddleware::class);
        $router->middleware(PreviewMiddleware::class);
        $router->middleware(ConvertStringToBoolean::class);
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
    }

    private function inspectFolderCommands(string $commandsSubpath)
    {
        $modules_namespace = config('modules.namespace');
        $files = glob(module_path($this->name, $commandsSubpath . DIRECTORY_SEPARATOR . '*.php'));

        return array_map(
            fn($file) => sprintf('%s\\%s\\%s\\%s', $modules_namespace, $this->name, Str::replace(['app/', '/'], ['', '\\'], $commandsSubpath), basename($file, '.php')),
            $files,
        );
    }

    /**
     * Get the services provided by the provider.
     */
    #[\Override]
    public function provides(): array
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->nameLower)) {
                $paths[] = $path . '/modules/' . $this->nameLower;
            }
        }

        return $paths;
    }

    protected function registerCache()
    {
        // Override the binding for the Repository
        $this->app->bind(BaseRepository::class, function ($app) {
            return new Repository($app->make(Store::class));
        });
        $this->app->bind(BaseContract::class, function ($app) {
            return new Repository($app->make(Store::class));
        });

        Cache::macro('tryByRequest', function (...$args) {
            return app(Repository::class)->tryByRequest(...$args);
        });

        Cache::macro('clearByEntity', function (...$args) {
            return app(Repository::class)->clearByEntity(...$args);
        });

        Cache::macro('clearByRequest', function (...$args) {
            return app(Repository::class)->clearByRequest(...$args);
        });

        Cache::macro('clearByUser', function (...$args) {
            return app(Repository::class)->clearByUser(...$args);
        });

        Cache::macro('clearByGroup', function (...$args) {
            return app(Repository::class)->clearByGroup(...$args);
        });
    }
}
