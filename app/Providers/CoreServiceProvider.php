<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Elastic\Elasticsearch\Client as ElasticsearchClient;
use Elastic\Elasticsearch\ClientBuilder;
use Exception;
use Filament\Forms\Components\Toggle;
use Illuminate\Console\Application as ArtisanApplication;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Console\Migrations\StatusCommand as LaravelStatusCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;
use Illuminate\Database\Migrations\Migrator as LaravelMigrator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Console\RouteListCommand as LaravelRouteListCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Features;
use Laravel\Scout\EngineManager;
use Modules\Core\ApplicationContent\ApplicationContentRetrievalProviderRegistry;
use Modules\Core\ApplicationContent\Contracts\ApplicationContentRetrievalProviderRegistryInterface;
use Modules\Core\Cache\CacheManager as CoreCacheManager;
use Modules\Core\Console\PruneMediaDraftsCommand;
use Modules\Core\Console\WarmCacheCommand;
use Modules\Core\Contracts\BootSampler;
use Modules\Core\Contracts\OutboxPublisher;
use Modules\Core\Exceptions\ConfigurationException;
use Modules\Core\Graph\Contracts\GraphProviderRegistryInterface;
use Modules\Core\Graph\Contracts\GraphToolGatewayInterface;
use Modules\Core\Graph\GraphProviderRegistry;
use Modules\Core\Graph\GraphToolGateway;
use Modules\Core\Http\Controllers\DocsController;
use Modules\Core\Http\Middleware\AddContext;
use Modules\Core\Http\Middleware\ApplyDatabaseSettingsOverlay;
use Modules\Core\Http\Middleware\EnsureCrudApiAreEnabled;
use Modules\Core\Http\Middleware\LocalizationMiddleware;
use Modules\Core\Http\Middleware\PreviewMiddleware;
use Modules\Core\Import\Events\ImportSessionCompleted;
use Modules\Core\Import\Events\ImportSessionFailed;
use Modules\Core\Import\Importers\UserImporter;
use Modules\Core\Import\Support\EntityImporterRegistry;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Listeners\SendImportFinishedNotification;
use Modules\Core\Locking\Locked;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\License;
use Modules\Core\Models\User as CoreUser;
use Modules\Core\Overrides\ContextualValidator;
use Modules\Core\Overrides\ListCommand as InternalListCommand;
use Modules\Core\Overrides\Migrator;
use Modules\Core\Overrides\ModuleServiceProvider;
use Modules\Core\Overrides\RouteListCommand;
use Modules\Core\Overrides\StatusCommand;
use Modules\Core\Performance\SubprocessBootSampler;
use Modules\Core\Search\Engines\ElasticsearchEngine;
use Modules\Core\Search\Engines\TypesenseEngine;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\DomainActionRegistry;
use Modules\Core\Services\DatabaseConfigOverlay;
use Modules\Core\Services\DynamicContentsService;
use Modules\Core\Services\ModerationAdapterRegistry;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Services\SettingsCacheCoordinator;
use Modules\Core\Services\StubOutboxPublisher;
use Modules\Core\SoftDeletes\SoftDeletes;
use Modules\Core\Versioning\ActiveVersionSet;
use Modules\Core\Versioning\Contracts\VersionSetManagerInterface;
use Modules\Core\Versioning\Contracts\VersionWriterInterface;
use Modules\Core\Versioning\VersionSetManager;
use Modules\Core\Versioning\VersionWriter;
use Nwidart\Modules\Module as NwidartModule;
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

        // HTTP requests get the overlay from ApplyDatabaseSettingsOverlay instead: a
        // long-lived worker boots once, so an overlay applied here would freeze
        // database-backed config for the whole process lifetime. Console commands have
        // no middleware pipeline, so they still need it at boot.
        if ($this->app->runningInConsole()) {
            $this->app->make(DatabaseConfigOverlay::class)
                ->applyFromDatabase($this->app->make(PerModelSettingResolver::class));
        }

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
        $this->configureFilamentDefaults();
        $this->registerModuleMacros();
        $this->registerValidationOverrides();
        $this->registerCacheWarmOnBoot();
        $this->registerImportEntities();
        $this->registerImportListeners();
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

        $this->registerCacheManager();

        $this->app->bind(OpenApiJsonController::class, DocsController::class);

        $this->app->register(FortifyServiceProvider::class);

        $this->app->singleton(DynamicContentsService::class, DynamicContentsService::getInstance(...));

        $this->app->register(GeocodingServiceProvider::class);

        // Singleton so every module registering at boot writes into the same
        // instance the dispatcher later reads.
        $this->app->singleton(DomainActionRegistry::class);

        // Singleton so every module registering its importable entities at boot
        // writes into the same registry the import framework later resolves from.
        $this->app->singleton(EntityImporterRegistry::class);

        $this->app->singleton(GraphProviderRegistryInterface::class, GraphProviderRegistry::class);
        $this->app->bind(GraphToolGatewayInterface::class, GraphToolGateway::class);
        $this->app->singleton(
            ApplicationContentRetrievalProviderRegistryInterface::class,
            ApplicationContentRetrievalProviderRegistry::class,
        );
        $this->app->bind(OutboxPublisher::class, StubOutboxPublisher::class);
        $this->app->bind(BootSampler::class, SubprocessBootSampler::class);
        $this->app->scoped(ActiveVersionSet::class);
        $this->app->scoped(VersionSetManagerInterface::class, VersionSetManager::class);
        $this->app->scoped(VersionWriterInterface::class, VersionWriter::class);

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
        $this->registerConsoleCommandOverrides();
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

            $schedule->command(PruneMediaDraftsCommand::class)->daily()->onOneServer();

            $crons = [];
            $cache_key = new ReflectionClass(CronJob::class)->newInstanceWithoutConstructor()->getTable();

            $cache = Cache::store();

            if ($this->cacheSupportsTags($cache)) {
                $cache_tags = $cache->getCacheTags();

                if (Cache::tags($cache_tags)->has($cache_key)) {
                    $crons = $this->normalizeCronJobs(Cache::tags($cache_tags)->get($cache_key));
                } else {
                    $crons = $this->loadCronJobsFromDatabase();

                    if ($crons !== []) {
                        Cache::tags($cache_tags)->put($cache_key, $crons);
                    }
                }
            } elseif (Cache::has($cache_key)) {
                $crons = $this->normalizeCronJobs(Cache::get($cache_key));
            } else {
                $crons = $this->loadCronJobsFromDatabase();

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
     * Register Core's own importable entities into the shared registry. Other
     * modules register theirs the same way from their providers' boot.
     */
    private function registerImportEntities(): void
    {
        $this->app->make(EntityImporterRegistry::class)
            ->register($this->app->make(UserImporter::class));
    }

    /**
     * Fan the terminal import events out to the in-app notification listener, so the
     * user who launched an import is told when it finishes or fails.
     */
    private function registerImportListeners(): void
    {
        Event::listen(ImportSessionCompleted::class, [SendImportFinishedNotification::class, 'handle']);
        Event::listen(ImportSessionFailed::class, [SendImportFinishedNotification::class, 'handle']);
    }

    /**
     * @return array<int, array{command: string, schedule: string}>
     */
    private function loadCronJobsFromDatabase(): array
    {
        try {
            // Live connection check — do not use SchemaInspector here. Its process-level
            // memoization survives across Pest tests while :memory: SQLite is wiped, which
            // falsely reports the table as present and floods logs with QueryExceptions.
            // Query the model's own connection (not the Schema facade default) to preserve
            // connection affinity.
            $cron_model = new CronJob;

            if (! $cron_model->getConnection()->getSchemaBuilder()->hasTable($cron_model->getTable())) {
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

                if ($schedule instanceof CronExpression) {
                    $schedule = $schedule->getExpression();
                }

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

    private function cacheSupportsTags(mixed $cache): bool
    {
        return is_object($cache)
            && method_exists($cache, 'supportsTags')
            && $cache->supportsTags()
            && method_exists($cache, 'getCacheTags');
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

        // Scoped, not singleton: the resolver holds an in-memory layer over a persistent
        // cache that only the writing process invalidates, so a process-wide instance
        // serves stale settings on every other worker until restart.
        $this->app->scoped(PerModelSettingResolver::class);
        $this->app->singleton(SettingsCacheCoordinator::class);
        $this->app->singleton(DatabaseConfigOverlay::class);

        // Scoped so that one request shares one instance: once() binds its memo to $this,
        // and a dozen collaborators resolve this service during a single CRUD request.
        $this->app->scoped(AuthorizationService::class);

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

    private function configureFilamentDefaults(): void
    {
        Toggle::configureUsing(
            static fn (Toggle $toggle): Toggle => $toggle->inline(false),
        );

        $this->bindLaraplateFilamentGenerators();
    }

    private function bindLaraplateFilamentGenerators(): void
    {
        $this->app->bind(
            \Filament\Commands\FileGenerators\Resources\ResourceClassGenerator::class,
            \Modules\Core\Filament\Generators\LaraplateResourceClassGenerator::class,
        );
        $this->app->bind(
            \Filament\Commands\FileGenerators\Resources\Schemas\ResourceTableClassGenerator::class,
            \Modules\Core\Filament\Generators\LaraplateResourceTableClassGenerator::class,
        );
        $this->app->bind(
            \Filament\Commands\FileGenerators\Resources\Schemas\ResourceFormSchemaClassGenerator::class,
            \Modules\Core\Filament\Generators\LaraplateResourceFormSchemaClassGenerator::class,
        );
        $this->app->bind(
            \Filament\Commands\FileGenerators\Resources\Schemas\ResourceInfolistSchemaClassGenerator::class,
            \Modules\Core\Filament\Generators\LaraplateResourceInfolistSchemaClassGenerator::class,
        );
        $this->app->bind(
            \Filament\Commands\FileGenerators\Resources\Pages\ResourceListRecordsPageClassGenerator::class,
            \Modules\Core\Filament\Generators\LaraplateResourceListRecordsPageClassGenerator::class,
        );
    }

    private function registerModuleMacros(): void
    {
        if (NwidartModule::hasMacro('isLaraplateOwned')) {
            return;
        }

        NwidartModule::macro(
            'isLaraplateOwned',
            fn (): bool => is_laraplate_owned_module($this->getName()),
        );
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

    private function registerConsoleCommandOverrides(): void
    {
        ArtisanApplication::starting(static function (ArtisanApplication $artisan): void {
            $artisan->add(new InternalListCommand());
        });

        $this->app->booted(function (): void {
            $this->app->loadDeferredProvider(LaravelRouteListCommand::class);

            $this->app->singleton(LaravelRouteListCommand::class, static function (Application $app): RouteListCommand {
                return new RouteListCommand($app['router']);
            });
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
     * Replace Laravel's cache manager so Cache:: resolves Core Repository
     * (tryByRequest, clearByEntity, …) for every configured store including failover.
     */
    private function registerCacheManager(): void
    {
        $this->app->singleton('cache', static fn ($app): CoreCacheManager => new CoreCacheManager($app));
        $this->app->singleton('cache.store', static fn ($app) => $app['cache']->driver());
        Cache::clearResolvedInstance();
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

    private function registerValidationOverrides(): void
    {
        Validator::resolver(static fn ($translator, $data, $rules, $messages, $attributes) => new ContextualValidator(
            $translator,
            $data,
            $rules,
            $messages,
            $attributes,
        ));
    }

    private function registerMiddlewares(): void
    {
        $router = resolve(Router::class);

        // The order inside each group is load-bearing. The overlay copies dotted
        // settings onto config, app.locale included, so it has to run before the locale
        // is resolved; AddContext records the resolved locale, so it runs last.
        // /admin declares its own stack and is wired in AdminPanelProvider instead.
        $surfaces = ['web' => 'app', 'api' => 'api'];

        foreach ($surfaces as $group => $scope) {
            // pushMiddlewareToGroup, not $router->middleware(): Router has no
            // middleware() method, so that call resolves through __call into a
            // RouteRegistrar which is built, never bound to a route, and discarded —
            // which is why these middleware had never run outside the panel.
            $router->pushMiddlewareToGroup($group, ApplyDatabaseSettingsOverlay::class);
            $router->pushMiddlewareToGroup($group, LocalizationMiddleware::class);
            // Request-scoped: ?preview=true arms HasApprovals for this call only.
            // The SPA re-sends the param when it wants the overlay; the session is
            // never touched on these surfaces.
            $router->pushMiddlewareToGroup($group, PreviewMiddleware::class . ':request');
            $router->pushMiddlewareToGroup($group, AddContext::class . ':' . $scope);
        }

        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
        $router->aliasMiddleware('crud_api', EnsureCrudApiAreEnabled::class);
    }
}
