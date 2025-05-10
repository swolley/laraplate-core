<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Override;
use Exception;
use TypeError;
use ReflectionException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Locking\Locked;
use Modules\Core\Models\CronJob;
use Illuminate\Support\Facades\DB;
use Modules\Core\Cache\Repository;
use Illuminate\Support\Facades\URL;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
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
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;

/**
 * @property \Illuminate\Foundation\Application $app
 */
final class CoreServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Core';

    protected string $nameLower = 'core';

    private array $subscribe = [
        LockedModelSubscriber::class,
    ];

    private array $listen = [
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

        Password::defaults(fn() => Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised());

        $this->configureCommands();
        $this->configureModels();
        $this->configureDates();
        $this->configureUrls();
    }

    /**
     * Register the service provider.
     */
    #[Override]
    public function register(): void
    {
        $this->registerConfig();
        $this->registerCache();

        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton(Locked::class, fn(): \Modules\Core\Locking\Locked => new Locked());
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
        Gate::before(fn(?User $user): ?true => $user && $user instanceof \Modules\Core\Models\User && $user->isSuperAdmin() ? true : null);
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

    /**
     * Get the services provided by the provider.
     */
    #[Override]
    public function provides(): array
    {
        return [];
    }

    /**
     * Configure the commands.
     */
    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    /**
     * Configure the models.
     */
    private function configureModels(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::shouldBeStrict();
    }

    /**
     * Configure the dates.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    /**
     * Configure the urls.
     */
    private function configureUrls(): void
    {
        if ($this->app->isProduction() && config('core.force_https')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Register commands in the format of Command::class.
     */
    private function registerCommands(): void
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
    }

    /**
     * Register command Schedules.
     */
    private function registerCommandSchedules(): void
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
                } catch (Exception $e) {
                    report($e);
                }
            }

            foreach ($crons as $cron) {
                $schedule->command($cron['command'])->cron($cron['schedule'])->onOneServer();
            }
        });
    }

    /**
     * @throws BindingResolutionException
     */
    private function registerMiddlewares(): void
    {
        $router = app('router');
        $router->middleware(LocalizationMiddleware::class);
        $router->middleware(PreviewMiddleware::class);
        $router->middleware(ConvertStringToBoolean::class);
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
    }

    /**
     * @throws InvalidArgumentException
     * @throws TypeError
     * @throws ReflectionException
     */
    private function registerCache(): void
    {
        // Override the binding for the Repository con il metodo corretto
        /** @phpstan-ignore-next-line */
        $this->app->extend('cache.store', fn($service, array $app): \Modules\Core\Cache\Repository => new Repository(
            $app['cache']->getStore(),
            $app['config']['cache.stores.' . $app['config']['cache.default']]
        ));

        // Ensure event dispatcher has been imported
        $this->app->resolving('cache.store', function (Repository $repository, array $app): \Modules\Core\Cache\Repository {
            $repository->setEventDispatcher($app['events']);
            return $repository;
        });

        // Bind interfaces to the correct service
        $this->app->bind(BaseRepository::class, fn($app) => $app['cache.store']);
        $this->app->bind(BaseContract::class, fn($app) => $app['cache.store']);
        $this->app->bind(Repository::class, fn($app) => $app['cache.store']);

        // Register macros
        Cache::macro('tryByRequest', fn(...$args) => app('cache.store')->tryByRequest(...$args));
        Cache::macro('clearByEntity', fn(...$args) => app('cache.store')->clearByEntity(...$args));
        Cache::macro('clearByRequest', fn(...$args) => app('cache.store')->clearByRequest(...$args));
        Cache::macro('clearByUser', fn(...$args) => app('cache.store')->clearByUser(...$args));
        Cache::macro('clearByGroup', fn(...$args) => app('cache.store')->clearByGroup(...$args));
    }

    private function inspectFolderCommands(string $commandsSubpath): array
    {
        $modules_namespace = config('modules.namespace');
        $files = glob(module_path($this->name, $commandsSubpath . DIRECTORY_SEPARATOR . '*.php'));

        return array_map(
            fn($file): string => sprintf('%s\\%s\\%s\\%s', $modules_namespace, $this->name, Str::replace(['app/', '/'], ['', '\\'], $commandsSubpath), basename($file, '.php')),
            $files,
        );
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
}
