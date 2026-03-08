<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use App\Models\User;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Carbon\CarbonImmutable;
use Elastic\Elasticsearch\Client as ElasticsearchClient;
use Elastic\Elasticsearch\ClientBuilder;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;
use Illuminate\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Scout\EngineManager;
use Modules\Core\Helpers\SoftDeletes;
use Modules\Core\Http\Middleware\AddContext;
use Modules\Core\Http\Middleware\ConvertStringToBoolean;
use Modules\Core\Http\Middleware\EnsureCrudApiAreEnabled;
use Modules\Core\Http\Middleware\LocalizationMiddleware;
use Modules\Core\Http\Middleware\PreviewMiddleware;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Locking\Locked;
use Modules\Core\Models\CronJob;
use Modules\Core\Overrides\ModuleServiceProvider;
use Modules\Core\Search\Engines\ElasticsearchEngine;
use Modules\Core\Search\Engines\TypesenseEngine;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Typesense\Client as TypesenseClient;

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

        throw_unless(is_subclass_of(user_class(), \Modules\Core\Models\User::class), Exception::class, 'User class is not ' . \Modules\Core\Models\User::class);

        $this->registerAuths();
        $this->registerMiddlewares();

        Password::defaults(static fn () => Password::min(8)
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
     *
     * @throws BindingResolutionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     */
    #[Override]
    public function register(): void
    {
        parent::register();

        $this->app->register(FortifyServiceProvider::class);

        // Register search clients
        $this->registerSearchClients();

        // Registration of custom search engines
        $this->registerSearchEngines();
    }

    public function registerAuths(): void
    {
        // bypass all other checks if the user is super admin
        Gate::before(static fn (?User $user): ?true => $user instanceof User && $user->isSuperAdmin() ? true : null);
    }

    /**
     * Register commands in the format of Command::class.
     */
    #[Override]
    protected function registerCommands(): void
    {
        $module_commands_subpath = config('modules.paths.generator.command.path');
        $commands = $this->inspectFolderCommands($module_commands_subpath);

        $locking_commands_subpath = (string) Str::replace('Console', 'Locking/Console', $module_commands_subpath);
        $locking_commands = $this->inspectFolderCommands($locking_commands_subpath);
        array_push($commands, ...$locking_commands);

        $search_commands_subpath = (string) Str::replace('Console', 'Search/Console', $module_commands_subpath);
        $search_commands = $this->inspectFolderCommands($search_commands_subpath);
        array_push($commands, ...$search_commands);

        $this->commands($commands);
    }

    /**
     * Register command Schedules.
     *
     * @throws BindingResolutionException
     */
    #[Override]
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function (): void {
            $schedule = $this->app->make(Schedule::class);
            $crons = [];
            $cache_key = new ReflectionClass(CronJob::class)->newInstanceWithoutConstructor()->getTable();

            /** @var Illuminate\Cache\Repository $cache * */
            $cache = Cache::store();

            if ($cache->supportsTags() && method_exists($cache, 'getCacheTags')) {
                $cache_tags = $cache->getCacheTags();

                if (Cache::tags($cache_tags)->has($cache_key)) {
                    $crons = Cache::tags($cache_tags)->get($cache_key) ?? [];
                } else {
                    $crons = $this->loadCronJobsFromDatabase($cache_key);

                    if ($crons !== []) {
                        Cache::tags($cache_tags)->put($cache_key, $crons);
                    }
                }
            } elseif (Cache::has($cache_key)) {
                $crons = Cache::get($cache_key) ?? [];
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
     * Load cron jobs from the database for the given table name.
     *
     * @return array<int, array{command: string, schedule: string}>
     */
    private function loadCronJobsFromDatabase(string $cache_key): array
    {
        try {
            if (! SchemaInspector::getInstance()->hasTable($cache_key)) {
                return [];
            }

            return CronJob::query()->active()->select(['command', 'schedule'])->get()->toArray();
        } catch (Exception $e) {
            report($e);

            return [];
        }
    }

    /**
     * Registers the search clients in the container.
     */
    private function registerSearchClients(): void
    {
        // Register Elasticsearch client
        $this->app->singleton(static function ($app): ElasticsearchClient {
            $config = config('elastic.client.connections.' . config('elastic.client.default', 'default'));

            return ClientBuilder::fromConfig($config);
        });

        // Register Typesense client
        $this->app->singleton(static function ($app): TypesenseClient {
            $config = config('scout.typesense.client-settings');

            return new TypesenseClient($config);
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
        $this->app->make(EngineManager::class)->extend('elasticsearch', static function ($app) {
            $config = config('search.engines.elasticsearch');

            // Get the Elasticsearch client from the container
            $client = $app->make(ElasticsearchClient::class);

            // Create the engine with proper dependency injection
            return $app->make(ElasticsearchEngine::class, [
                'client' => $client,
                'config' => $config,
            ]);
        });

        $this->app->make(EngineManager::class)->extend('typesense', static function ($app) {
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

    /**
     * Configure the models.
     */
    private function configureModels(): void
    {
        // TODO: should be strict prevents also eager loading. App is not yet ready for this
        // Model::shouldBeStrict();
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());
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
