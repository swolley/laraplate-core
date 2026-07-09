<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Modules\Core\Exceptions\ConfigurationException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Traits\PathNamespace;
use Override;

class ModuleServiceProvider extends ServiceProvider
{
    use PathNamespace;

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
    }

    public function register(): void
    {
        if ($this->name !== 'Core') {
            throw_unless(Module::find('Core'), ConfigurationException::class, 'Core is required and must be enabled');
        }

        $this->registerConfig();

        $namespace = $this->module_namespace($this->name, $this->app_path(config('modules.paths.generator.provider.path')));
        $namespace = str_replace('\\App\\', '\\', $namespace);

        $this->app->register($namespace . '\EventServiceProvider');
        $this->app->register($namespace . '\RouteServiceProvider');
    }

    /**
     * Register translations on Laravel's default namespace (filename = group prefix).
     *
     * Module-specific strings belong in lang/{locale}/{module}.php (e.g. core.php).
     * Shared Laravel groups (validation, auth, pagination, …) use their standard filenames.
     */
    public function registerTranslations(): void
    {
        $langPath = $this->getResourcePath('lang');

        if (is_dir($langPath)) {
            $this->registerDefaultTranslationPath($langPath);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $lang_path = module_path($this->name, 'lang');

            if (is_dir($lang_path)) {
                $this->registerDefaultTranslationPath($lang_path);
                $this->loadJsonTranslationsFrom($lang_path);
            }
        }
    }

    protected function registerDefaultTranslationPath(string $lang_path): void
    {
        $this->callAfterResolving('translator', function ($translator) use ($lang_path): void {
            $translator->addPath($lang_path);
        });
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
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = $this->getResourcePath('views');
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

        $componentNamespace = $this->module_namespace($this->name, $this->app_path(config('modules.paths.generator.component-class.path')));
        Blade::componentNamespace($componentNamespace, $this->nameLower);
    }

    /**
     * Register commands in the format of Command::class.
     */
    protected function registerCommands(): void
    {
        $module_commands_subpath = config('modules.paths.generator.command.path');
        $commands = $this->inspectFolderCommands($module_commands_subpath);

        $this->commands($commands);
    }

    protected function inspectFolderCommands(string $commandsSubpath): array
    {
        $modules_namespace = config('modules.namespace');
        $files = glob(module_path($this->name, $commandsSubpath . DIRECTORY_SEPARATOR . '*.php'));

        $classes = array_map(
            fn (string $file): string => sprintf(
                '%s\\%s\\%s\\%s',
                $modules_namespace,
                $this->name,
                Str::replace(['app/', '/'], ['', '\\'], $commandsSubpath),
                basename($file, '.php'),
            ),
            $files,
        );

        // Only return real console commands. This prevents helper or test-only
        // classes under the Console namespace from being registered and causing
        // type errors when Artisan boots.
        return array_values(array_filter(
            $classes,
            static fn (string $class): bool => is_subclass_of($class, Command::class),
        ));
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // $this->app->booted(function () {
        //     $schedule = $this->app->make(Schedule::class);
        //     $schedule->command('inspire')->hourly();
        // });
    }

    private function getResourcePath(string $prefix): string
    {
        return resource_path($prefix . '/modules/' . $this->nameLower);
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
