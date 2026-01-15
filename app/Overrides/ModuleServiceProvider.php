<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Exception;
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
            throw_unless(Module::find('Core'), Exception::class, 'Core is required and must be enabled');
        }

        $this->registerConfig();

        $namespace = $this->module_namespace($this->name, $this->app_path(config('modules.paths.generator.provider.path')));
        $namespace = str_replace('\\App\\', '\\', $namespace);

        $this->app->register($namespace . '\EventServiceProvider');
        $this->app->register($namespace . '\RouteServiceProvider');
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = $this->getResourcePath('lang');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $lang_path = module_path($this->name, 'lang');
            $this->loadTranslationsFrom($lang_path, $this->nameLower);
            $this->loadJsonTranslationsFrom($lang_path);
        }
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

        return array_map(
            fn ($file): string => sprintf('%s\\%s\\%s\\%s', $modules_namespace, $this->name, Str::replace(['app/', '/'], ['', '\\'], $commandsSubpath), basename($file, '.php')),
            $files,
        );
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
