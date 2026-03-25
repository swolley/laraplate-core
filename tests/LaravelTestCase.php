<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Illuminate\Cache\ArrayStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Modules\Core\Cache\Repository as CoreCacheRepository;
use Modules\Core\Helpers\HelpersCache;
use Modules\Core\Http\Middleware\EnsureCrudApiAreEnabled;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Models\Version;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use Throwable;

/**
 * Base test case for tests that need a full Laravel application (Auth, DB, modules).
 * Uses Orchestra Testbench to boot a minimal Laravel app with the Core module loaded.
 */
abstract class LaravelTestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * Directory used as modules path so nwidart discovers Core (this repo) before app boots.
     */
    private static ?string $testbenchModulesPath = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureCoolsamModulesResourceAlias();
        $this->ensureAuthConfigForSpatiePermission();
        $this->loadMigrationsFrom(self::testbench_migrations_path());
        $this->loadMigrationsFrom(self::moduleMigrationsPath());
        $this->ensureTestCacheStore();
        $this->ensureCoreRoutesRegistered();
        $this->ensureModelsCachePopulated();
    }

    /**
     * Get package providers required for the Core module (nwidart discovers Core via config).
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Nwidart\Modules\LaravelModulesServiceProvider::class,
            \Laravel\Socialite\SocialiteServiceProvider::class,
        ];
    }

    /**
     * Define environment setup so that the Core module is discoverable and auth uses Core User.
     * Creates a fixed modules path with Core symlinked to this repo so nwidart finds it on boot.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $path = self::ensureTestbenchModulesPath();
        $app['config']->set('modules.paths.modules', $path);
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.guards.api', ['driver' => 'token', 'provider' => 'users']);
        $app['config']->set('auth.providers.users', ['driver' => 'eloquent', 'model' => User::class]);
        $app['config']->set('app.available_locales', ['en', 'it', 'de', 'es', 'sl']);
        $app['config']->set('core.expose_crud_api', true);
        $app['config']->set('crud.dynamic_entities', true);
        $app['config']->set('cache.duration', 3600);

        self::mergePermissionConfig($app);
        $app['config']->set('versionable.version_model', Version::class);
        $app['config']->set('versionable.user_model', User::class);
        $app['config']->set('versionable.user_foreign_key', 'user_id');
        $app['config']->set('versionable.keep_versions', 0);
        $app['config']->set('versionable.uuid', false);

        if (extension_loaded('pdo_sqlite')) {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections.sqlite', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        } else {
            // SQLite driver not available: use MySQL (set DB_* env or create database "core_test")
            $app['config']->set('database.default', 'mysql');
            $db_name = env('DB_DATABASE', 'core_test');

            if ($db_name === ':memory:') {
                $db_name = 'core_test';
            }
            $app['config']->set('database.connections.mysql', [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => $db_name,
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ]);
        }
    }

    /**
     * Path to test-only migrations (e.g. base users table) that must run before module migrations.
     */
    private static function testbench_migrations_path(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    }

    /**
     * Absolute path to the Core module migrations (repo root database/migrations).
     */
    private static function moduleMigrationsPath(): string
    {
        return realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations') ?: dirname(__DIR__) . '/database/migrations';
    }

    /**
     * Ensure .testbench-modules exists and Core symlinks to the repo root (so nwidart can load it).
     */
    private static function ensureTestbenchModulesPath(): string
    {
        if (self::$testbenchModulesPath !== null) {
            return self::$testbenchModulesPath;
        }

        $repo_root = realpath(dirname(__DIR__));
        $modules_dir = $repo_root . DIRECTORY_SEPARATOR . '.testbench-modules';

        if (! is_dir($modules_dir)) {
            mkdir($modules_dir, 0755, true);
        }

        $core_link = $modules_dir . DIRECTORY_SEPARATOR . 'Core';

        if (! file_exists($core_link) && $repo_root !== false) {
            symlink($repo_root, $core_link);
        }

        self::$testbenchModulesPath = $modules_dir;

        return self::$testbenchModulesPath;
    }

    /**
     * Merge Core module permission config so config('permission.models.permission') etc. resolve.
     */
    private static function mergePermissionConfig($app): void
    {
        $app['config']->set('permission.models.permission', Permission::class);
        $app['config']->set('permission.models.role', Role::class);
        $app['config']->set('permission.roles.superadmin', env('SUPERADMIN_ROLE', 'superadmin'));
        $app['config']->set('permission.roles.admin', env('ADMIN_ROLE', 'admin'));
        $app['config']->set('permission.roles.guest', env('GUEST_ROLE', 'guest'));
        $app['config']->set('permission.users.superadmin', env('SUPERADMIN_USER', 'superadmin'));
        $app['config']->set('permission.users.admin', env('ADMIN_USER', 'admin'));
        $app['config']->set('permission.users.guest', env('GUEST_USER', 'anonymous'));
        $app['config']->set('permission.table_names.roles', 'roles');
        $app['config']->set('permission.table_names.permissions', 'permissions');
        $app['config']->set('permission.table_names.model_has_permissions', 'model_has_permissions');
        $app['config']->set('permission.table_names.model_has_roles', 'model_has_roles');
        $app['config']->set('permission.table_names.role_has_permissions', 'role_has_permissions');
        $app['config']->set('permission.column_names.role_pivot_key', 'role_id');
        $app['config']->set('permission.column_names.permission_pivot_key', 'permission_id');
        $app['config']->set('permission.column_names.model_morph_key', 'model_id');
        $app['config']->set('permission.column_names.team_foreign_key', 'team_id');
        $app['config']->set('permission.teams', false);
        $app['config']->set('permission.cache.key', 'spatie.permission.cache');
        $app['config']->set('permission.cache.store', 'array');
    }

    /**
     * Ensure auth guards and providers are set so Spatie Permission's getModelForGuard() resolves to User::class.
     * Must run after app boot so we override any config loaded from config/auth.php.
     */
    private function ensureAuthConfigForSpatiePermission(): void
    {
        config([
            'auth.defaults.guard' => 'web',
            'auth.guards.web' => ['driver' => 'session', 'provider' => 'users'],
            'auth.guards.api' => ['driver' => 'token', 'provider' => 'users'],
            'auth.providers.users' => ['driver' => 'eloquent', 'model' => User::class],
        ]);
    }

    /**
     * Ensure Coolsam\Modules\Resource resolves in tests even when the Coolsam package is not installed.
     * In the real app this class is provided by the main repository; for Core tests we can safely alias it to Filament's base Resource.
     */
    private function ensureCoolsamModulesResourceAlias(): void
    {
        if (class_exists(\Coolsam\Modules\Resource::class)) {
            return;
        }

        if (! class_exists(\Filament\Resources\Resource::class)) {
            return;
        }

        class_alias(\Filament\Resources\Resource::class, \Coolsam\Modules\Resource::class);
    }

    /**
     * Populate HelpersCache with Core models when empty (Testbench may not have Module::allEnabled() returning Core).
     * Ensures tryResolveModel('users', null) etc. work in CRUD API tests.
     */
    private function ensureModelsCachePopulated(): void
    {
        if (HelpersCache::getModels('active') !== null) {
            return;
        }

        $repo_root = realpath(dirname(__DIR__));

        if ($repo_root === false) {
            return;
        }

        $models_path = $repo_root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Models';

        if (! is_dir($models_path)) {
            return;
        }

        $namespace = 'Modules\\Core\\Models\\';
        $cached = [];

        foreach (File::allFiles($models_path) as $model_file) {
            if ($model_file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(
                ['/', '\\'],
                '\\',
                mb_substr($model_file->getRelativePathname(), 0, -4),
            );
            $class = $namespace . $relative;

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            try {
                if ((new ReflectionClass($class))->isAbstract()) {
                    continue;
                }
            } catch (Throwable) {
                continue;
            }

            $cached[] = $class;
        }

        if ($cached !== []) {
            HelpersCache::setModels('active', $cached);
        }
    }

    /**
     * Ensure Core API and web routes are registered (Testbench may not run module RouteServiceProvider in time).
     */
    private function ensureCoreRoutesRegistered(): void
    {
        $router = $this->app->make('router');

        if ($router->getRoutes()->getByName('core.api.list') !== null) {
            return;
        }

        $router->aliasMiddleware('crud_api', EnsureCrudApiAreEnabled::class);

        $path = realpath(dirname(__DIR__));

        if ($path === false) {
            return;
        }

        $router->prefix('api/v1')
            ->middleware(['api', 'crud_api'])
            ->name('core.api.')
            ->group(function () use ($path): void {
                require $path . '/routes/crud.php';

                require $path . '/routes/api.php';
            });

        // Auth routes (userInfo, impersonate, leaveImpersonate, maintainSession) for UserControllerTest
        $router->middleware(['web', 'auth'])
            ->prefix('app/auth')
            ->name('core.')
            ->group(function () use ($path): void {
                require $path . '/routes/auth.php';
            });

        $router->getRoutes()->refreshNameLookups();
    }

    /**
     * Override cache.store and cache manager so default driver returns Core Repository (has getCacheTags).
     */
    private function ensureTestCacheStore(): void
    {
        $app = $this->app;
        $store = new ArrayStore;
        $config = $app['config']->get('cache.stores.array', []);
        $repository = new CoreCacheRepository($store, $config);
        $repository->setEventDispatcher($app['events']);
        $app->instance('cache.store', $repository);
        $app->instance('cache', new class($app) extends \Illuminate\Cache\CacheManager
        {
            public function store($name = null)
            {
                $name = $name ?? $this->getDefaultDriver();

                if ($name === 'array' || $name === $this->getDefaultDriver()) {
                    return $this->app['cache.store'];
                }

                return parent::store($name);
            }
        });
    }
}
