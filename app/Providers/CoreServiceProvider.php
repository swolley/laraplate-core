<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Carbon\CarbonImmutable;
use Elastic\Elasticsearch\Client as ElasticsearchClient;
use Elastic\Elasticsearch\ClientBuilder;
use Exception;
use Modules\Core\Exceptions\ConfigurationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Console\Migrations\StatusCommand as LaravelStatusCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;
use Illuminate\Database\Migrations\Migrator as LaravelMigrator;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;
use Laravel\Scout\EngineManager;
use Modules\Core\Cache\Repository as CoreCacheRepository;
use Modules\Core\Console\WarmCacheCommand;
use Modules\Core\Http\Controllers\DocsController;
use Modules\Core\Http\Middleware\AddContext;
use Modules\Core\Http\Middleware\ConvertStringToBoolean;
use Modules\Core\Http\Middleware\EnsureCrudApiAreEnabled;
use Modules\Core\Http\Middleware\LocalizationMiddleware;
use Modules\Core\Http\Middleware\PreviewMiddleware;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Locking\Locked;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\License;
use Modules\Core\Models\User as CoreUser;
use Modules\Core\Overrides\Migrator;
use Modules\Core\Overrides\ModuleServiceProvider;
use Modules\Core\Overrides\StatusCommand;
use Modules\Core\Search\Engines\ElasticsearchEngine;
use Modules\Core\Search\Engines\TypesenseEngine;
use Modules\Core\Services\DatabaseConfigOverlay;
use Modules\Core\Services\DynamicContentsService;
use Modules\Core\Services\ModerationAdapterRegistry;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Services\SettingsCacheCoordinator;
use Modules\Core\SoftDeletes\SoftDeletes;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Typesense\Client as TypesenseClient;
use Wotz\SwaggerUi\Http\Controllers\OpenApiJsonController;

/**
 * @property Application $app
 */
final class CoreServiceProvider extends ModuleServiceProvider
{
    #[Override]
    protected string $name = 'Core';

    #[Override]
    protected string $nameLower = 'core';

    /**
     * Boot the application events.
     *
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        parent::boot();

        $this->app->make(DatabaseConfigOverlay::class)
            ->applyFromDatabase($this->app->make(PerModelSettingResolver::class));
        $this->configureFortifyFeatures();

        $this->registerAuths();
        $this->registerMiddlewares();

        Password::defaults(function (): Password {
            $rule = Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();

            if ($this->app->environment('testing')) {
                return $rule;
            }

            return $rule->uncompromised();
        });

        $this->configureCommands();
        $this->configureModels();
        $this->configureDates();
        $this->configureUrls();
        $this->registerCacheWarmOnBoot();
    }

    /**
     * Register the service provider.
     *
     * @throws BindingResolutionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    #[Override]
    public function register(): void
    {
        throw_unless(is_subclass_of(user_class(), CoreUser::class), ConfigurationException::class, 'User class is not ' . CoreUser::class);

        parent::register();

        $this->app->bind(OpenApiJsonController::class, DocsController::class);

        $this->app->register(FortifyServiceProvider::class);

        $this->app->singleton(DynamicContentsService::class, DynamicContentsService::getInstance(...));

        $this->app->register(GeocodingServiceProvider::class);

        // Register search clients
        $this->registerSearchClients();

        // Registration of custom search engines
        $this->registerSearchEngines();

        $oci8_provider = \Yajra\Oci8\Oci8ServiceProvider::class;
        $oci8_validation_provider = \Yajra\Oci8\Oci8ValidationServiceProvider::class;

        if (extension_loaded('oci8')
            && class_exists($oci8_provider)
            && class_exists($oci8_validation_provider)) {
            $this->app->register($oci8_provider);
            $this->app->register($oci8_validation_provider);
        }

        $this->registerMigrationOverrides();
    }

    public function registerAuths(): void
    {
        // bypass all other checks if the user is super admin
        Gate::before(static fn (?CoreUser $user): ?true => $user instanceof CoreUser && $user->isSuperAdmin() ? true : null);
    }

    /**
     * Register commands in the format of Command::class.
     */
    protected function registerCommands(): void
    {
        $module_commands_subpath = config('modules.paths.generator.command.path');

        if (! is_string($module_commands_subpath) || $module_commands_subpath === '') {
            return;
        }

        $commands = $this->inspectFolderCommands($module_commands_subpath);

        $locking_commands_subpath = Str::replace('Console', 'Locking/Console', $module_commands_subpath);
        $locking_commands = $this->inspectFolderCommands($locking_commands_subpath);
        array_push($commands, ...$locking_commands);

        $search_commands_subpath = Str::replace('Console', 'Search/Console', $module_commands_subpath);
        $search_commands = $this->inspectFolderCommands($search_commands_subpath);
        array_push($commands, ...$search_commands);

        $soft_deletes_commands_subpath = Str::replace('Console', 'SoftDeletes/Console', $module_commands_subpath);
        $soft_deletes_commands = $this->inspectFolderCommands($soft_deletes_commands_subpath);
        array_push($commands, ...$soft_deletes_commands);

        $this->commands($commands);
    }

    /**
     * Register command Schedules.
     *
     * @throws BindingResolutionException
     */
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);
            $crons = [];
            $cache_key = new ReflectionClass(CronJob::class)->newInstanceWithoutConstructor()->getTable();

            $cache = Cache::store();

            if ($cache instanceof CoreCacheRepository && $cache->supportsTags()) {
                $cache_tags = $cache->getCacheTags();

                if (Cache::tags($cache_tags)->has($cache_key)) {
                    $crons = $this->normalizeCronJobs(Cache::tags($cache_tags)->get($cache_key));
                } else {
                    $crons = $this->loadCronJobsFromDatabase($cache_key);

                    if ($crons !== []) {
                        Cache::tags($cache_tags)->put($cache_key, $crons);
                    }
                }
            } elseif (Cache::has($cache_key)) {
                $crons = $this->normalizeCronJobs(Cache::get($cache_key));
            } else {
                $crons = $this->loadCronJobsFromDatabase($cache_key);

                if ($crons !== []) {
                    Cache::put($cache_key, $crons);
                }
            }

            foreach ($crons as $cron) {
                $schedule->command($cron['command'])->cron($cron['schedule'])->onOneServer();
            }
        });
    }

    /**
     * @return array<int, array{command: string, schedule: string}>
     */
    private function loadCronJobsFromDatabase(string $cache_key): array
    {
        try {
            if (! SchemaInspector::getInstance()->hasTable($cache_key)) {
                return [];
            }

            $cron_jobs = CronJob::query()
                ->active()
                ->select(['command', 'schedule'])
                ->get();

            $normalized = [];

            foreach ($cron_jobs as $cron_job) {
                $command = $cron_job->getAttribute('command');
                $schedule = $cron_job->getAttribute('schedule');

                if (! is_scalar($command) || ! is_scalar($schedule)) {
                    continue;
                }

                $normalized[] = [
                    'command' => (string) $command,
                    'schedule' => (string) $schedule,
                ];
            }

            return $normalized;
        } catch (Exception $e) {
            report($e);

            return [];
        }
    }

    /**
     * @return array<int, array{command: string, schedule: string}>
     */
    private function normalizeCronJobs(mixed $crons): array
    {
        if (! is_array($crons)) {
            return [];
        }

        $normalized = [];

        foreach ($crons as $cron) {
            if (! is_array($cron)) {
                continue;
            }

            $command = $cron['command'] ?? null;
            $schedule = $cron['schedule'] ?? null;

            if (! is_string($command) || ! is_string($schedule)) {
                continue;
            }

            $normalized[] = [
                'command' => $command,
                'schedule' => $schedule,
            ];
        }

        return $normalized;
    }

    /**
     * Registers the search clients in the container.
     */
    private function registerSearchClients(): void
    {
        // Register Elasticsearch client
        $this->app->singleton(static function (Application $app): ElasticsearchClient {
            $default_connection = config('elastic.client.default', 'default');
            $default_connection = is_string($default_connection) ? $default_connection : 'default';
            $config = config('elastic.client.connections.' . $default_connection);

            return ClientBuilder::fromConfig(is_array($config) ? $config : []);
        });

        // Register Typesense client
        $this->app->singleton(static function (Application $app): TypesenseClient {
            $config = config('scout.typesense.client-settings');

            return new TypesenseClient((array) $config);
        });
    }

    /**
     * Registers the custom search engines.
     *
     * @throws BindingResolutionException
     */
    private function registerSearchEngines(): void
    {
        // Extend Laravel Scout with custom engines
        $this->app->make(EngineManager::class)->extend('elasticsearch', static function (Application $app) {
            $config = config('search.engines.elasticsearch');

            // Get the Elasticsearch client from the container
            $client = $app->make(ElasticsearchClient::class);

            // Create the engine with proper dependency injection
            return $app->make(ElasticsearchEngine::class, [
                'client' => $client,
                'config' => $config,
            ]);
        });

        $this->app->make(EngineManager::class)->extend('typesense', static function (Application $app) {
            $config = config('search.engines.typesense');

            // Get the Typesense client from the container
            $client = $app->make(TypesenseClient::class);

            // Create the engine with proper dependency injection
            return $app->make(TypesenseEngine::class, [
                'client' => $client,
                'config' => $config,
                'maxTotalResults' => config('scout.typesense.max_total_results', 1000),
            ]);
        });

        $this->app->singleton(Locked::class, static fn (): Locked => new Locked());
        $this->app->alias(Locked::class, 'locked');

        $this->app->singleton(SchemaInspector::class, static fn (): SchemaInspector => SchemaInspector::getInstance());

        $this->app->singleton(PerModelSettingResolver::class);
        $this->app->singleton(SettingsCacheCoordinator::class);
        $this->app->singleton(DatabaseConfigOverlay::class);

        $this->app->singleton(ModerationAdapterRegistry::class);

        $this->app->alias(BaseSoftDeletes::class, SoftDeletes::class);

        if ($this->app->isLocal()) {
            $this->app->register(IdeHelperServiceProvider::class);
        }
    }

    /**
     * Configure the commands.
     */
    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands($this->app->isProduction());
    }

    private function configureFortifyFeatures(): void
    {
        $features = [
            Features::resetPasswords(),
            Features::updateProfileInformation(),
            Features::updatePasswords(),
        ];

        if (config('core.enable_user_registration')) {
            $features[] = Features::registration();
        }

        if (config('core.verify_new_user')) {
            $features[] = Features::emailVerification();
        }

        if (config('core.enable_user_2fa')) {
            $features[] = Features::twoFactorAuthentication([
                'confirm' => true,
                'confirmPassword' => true,
            ]);
        }

        config()->set('fortify.features', $features);
    }

    private function registerMigrationOverrides(): void
    {
        $this->app->booted(function (): void {
            $this->app->loadDeferredProvider('migrator');

            $this->app->singleton('migrator', static function (Application $app): Migrator {
                return new Migrator(
                    $app['migration.repository'],
                    $app['db'],
                    $app['files'],
                    $app['events'] ?? null,
                );
            });

            $this->app->singleton(LaravelStatusCommand::class, static function (Application $app): StatusCommand {
                return new StatusCommand($app['migrator']);
            });

            $this->app->bind(LaravelMigrator::class, static fn (Application $app): Migrator => $app['migrator']);
        });
    }

    /**
     * Configure the models.
     */
    private function configureModels(): void
    {
        // TODO: should be strict prevents also eager loading. App is not yet ready for this
        // Model::shouldBeStrict();
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        License::creating(function (License $license): void {
            if ($license->uuid === null || $license->uuid === '') {
                $license->uuid = (string) Str::uuid();
            }
        });
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
     * Register the cache warm-on-boot hook.
     *
     * When `core.cache.warm_on_boot` is true, the cache warming command is
     * executed after all service providers have been registered, ensuring
     * all bindings are available before warming begins.
     */
    private function registerCacheWarmOnBoot(): void
    {
        if (! config('core.cache.warm_on_boot', false)) {
            return;
        }

        $this->app->booted(function (): void {
            $this->app->make(WarmCacheCommand::class)->handle();
        });
    }

    private function registerMiddlewares(): void
    {
        $router = resolve(Router::class);
        $router->middleware(LocalizationMiddleware::class);
        $router->middleware(PreviewMiddleware::class);
        $router->middleware(ConvertStringToBoolean::class);
        $router->middleware(AddContext::class);
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
        $router->aliasMiddleware('crud_api', EnsureCrudApiAreEnabled::class);
    }
}
