<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Minimal container for standalone testing
|--------------------------------------------------------------------------
|
| Sets up a lightweight container with just enough bindings so that
| Laravel helpers (now(), config(), …) work without booting a full app.
|
*/

$container = new Illuminate\Container\Container();

$container->singleton('config', static fn (): Illuminate\Config\Repository => new Illuminate\Config\Repository([
    'app' => ['locale' => 'en', 'timezone' => 'UTC', 'fallback_locale' => 'en'],
]));

$container->singleton('date', static fn (): Illuminate\Support\DateFactory => new Illuminate\Support\DateFactory());

Illuminate\Support\Facades\Facade::setFacadeApplication($container);
Illuminate\Container\Container::setInstance($container);

// Register test fixture namespaces that the Composer autoloader doesn't know about
spl_autoload_register(static function (string $class): void {
    $map = [
        'Modules\\Fake\\Models\\' => __DIR__ . '/Fixtures/',
    ];

    foreach ($map as $prefix => $base_dir) {
        if (str_starts_with($class, $prefix)) {
            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $base_dir . $relative . '.php';

            if (file_exists($file)) {
                require $file;
            }

            return;
        }
    }
});

if (! function_exists('module_path')) {
    /**
     * Standalone stub: resolves paths relative to the module root.
     */
    function module_path(string $module, string $path = ''): string
    {
        return dirname(__DIR__) . '/' . ltrim($path, '/');
    }
}
