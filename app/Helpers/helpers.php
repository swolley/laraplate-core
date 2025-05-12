<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nwidart\Modules\Module;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

if (! function_exists('modules')) {
    /**
     * get list of available modules.
     *
     * @param  bool  $showMainApp  add main app into modules list
     * @param  bool  $fullpath  return only module name or full path on file system
     * @param  bool  $onlyActive  return only active modules
     * @param  null|string  $onlyModule  filter for specified module
     * @param  null|bool  $prioritySort  sort modules by priority
     * @return array<int,string>
     */
    function modules(bool $showMainApp = false, bool $fullpath = false, bool $onlyActive = true, ?string $onlyModule = null, ?bool $prioritySort = false): array
    {
        $module_class = Nwidart\Modules\Facades\Module::class;
        $modules = class_exists($module_class) ? ($onlyActive ? $module_class::allEnabled() : $module_class::all()) : [];
        $remapped_modules = [];

        foreach ($modules as $module => $class) {
            $remapped_modules[ucfirst($module)] = $class;
        }

        if ($onlyModule !== null && $onlyModule !== '' && $onlyModule !== '0') {
            $onlyModule = ucfirst($onlyModule);
            $remapped_modules = array_filter($remapped_modules, fn (string $k): bool => $k === $onlyModule || $onlyModule === null, ARRAY_FILTER_USE_KEY);
        }

        if ($prioritySort === true) {
            uasort($remapped_modules, fn (Module $a, Module $b): int => $b->getPriority() <=> $a->getPriority());
        }

        $remapped_modules = $fullpath ? array_map(fn (Module $m): string => $m->getPath(), $remapped_modules) : array_keys($remapped_modules);

        if ($showMainApp && ($onlyModule === null || $onlyModule === '' || $onlyModule === '0' || $onlyModule === 'App')) {
            if ($fullpath) {
                $remapped_modules['App'] = app_path();
            } else {
                array_unshift($remapped_modules, 'App');
            }
            ksort($remapped_modules);
        }

        return $remapped_modules;
    }
}

if (! function_exists('normalize_path')) {
    /**
     * get list of available translations.
     *
     * @param  string  $p  return path with correct directory separator
     */
    function normalize_path(string $p): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $p);
    }
}

if (! function_exists('connections')) {
    /**
     * get list of connections from models.
     *
     * @param  bool  $onlyActive  filter for only active modules
     * @return array<string>
     */
    function connections(bool $onlyActive = true): array
    {
        $connections = [];

        if (! $onlyActive) {
            foreach (config('database.connections', []) as $connection) {
                $driver = $connection->getDriverName();

                if (! in_array($driver, $connections, true)) {
                    $connections[] = $driver;
                }
            }

            return $connections;
        }

        foreach (models($onlyActive) as $model) {
            $connection = new $model()->getConnection();
            $driver = $connection->getDriverName();

            if (! in_array($driver, $connections, true)) {
                $connections[] = $driver;
            }
        }

        return $connections;
    }
}

if (! function_exists('translations')) {
    /**
     * get list of available translations.
     *
     * @param  bool  $fullpath  return only unique translation code or full all paths on file system
     * @param  bool  $onlyActive  filter for only active modules
     * @return array<string>
     */
    function translations(bool $fullpath = false, bool $onlyActive = true): array
    {
        $app_dir = base_path('lang');
        $app_languages = glob($app_dir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR);
        $langs_subpath = config('modules.paths.generator.lang.path');

        $modules_languages = $fullpath ? $app_languages : array_map(fn (string $l): string => str_replace($app_dir . DIRECTORY_SEPARATOR, '', $l), $app_languages);

        foreach (modules(false, true, $onlyActive) as $module) {
            $is_app = (bool) preg_match("/[\\\\\/]app$/", $module);
            $path = $module . DIRECTORY_SEPARATOR . ($is_app ? 'lang' : $langs_subpath) . DIRECTORY_SEPARATOR;
            $files = glob("{$path}*", GLOB_ONLYDIR);

            foreach ($files as $file) {
                if (! $fullpath) {
                    $exploded = explode(DIRECTORY_SEPARATOR, $file);
                    $language = array_pop($exploded);

                    if (! in_array($language, $modules_languages, true)) {
                        $modules_languages[] = $language;
                    }
                } else {
                    $modules_languages[] = $file;
                }
            }
        }

        sort($modules_languages);

        return $modules_languages;
    }
}

if (! function_exists('migrations')) {
    /**
     * check if there are pending migrations.
     *
     * @param  bool  $count  return only count or full list
     * @param  bool  $onlyPending  return only pending migrations
     * @param  bool  $onlyActive  filter for only active modules
     * @param  null|string  $onlyModule  filter for specified module
     * @return false|int|array<string> number if count requested, string[] if list requested, false if error occured
     */
    function migrations(bool $count = false, bool $onlyPending = false, bool $onlyActive = true, ?string $onlyModule = null): array|int|false
    {
        try {
            $found = [];

            foreach (modules(true, false, $onlyActive, $onlyModule) as $m) {
                $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);

                if ($m === 'App') {
                    Artisan::call('migrate:status', outputBuffer: $output);
                } else {
                    Artisan::call('module:migrate-status', ['module' => $m], $output);
                }

                $content = explode(PHP_EOL, $output->fetch());

                foreach ($content as $line) {
                    $trimmed = mb_trim($line);

                    if (preg_match("/^\d{4}_\d{2}_\d{2}_\d{6}_/", $trimmed) === 1 && (preg_match('/Ran$/', $trimmed) === 0 || ! $onlyPending)) {
                        $found[] = $line;
                    }
                }
            }

            return $count ? count($found) : $found;
        } catch (Exception) {
            return false;
        }
    }
}

if (! function_exists('models')) {
    /**
     * list all Models.
     *
     * @param  bool  $onlyActive  filter for only active modules
     * @param  null|string  $onlyModule  filter for specified module
     * @return list<class-string<Model>>
     */
    function models(bool $onlyActive = true, ?string $onlyModule = null): array
    {
        $models = [];
        $modules = modules(true, true, $onlyActive, $onlyModule);

        foreach ($modules as $m) {
            $is_app = (bool) preg_match("/[\\\\\/]app$/", $m);
            $modules_models_folder = config('modules.paths.generator.model.path');
            $models_path = $m . DIRECTORY_SEPARATOR . ($is_app ? 'Models' : $modules_models_folder);
            $model_files = File::allFiles($models_path);

            if ($model_files === []) {
                continue;
            }

            if ($is_app) {
                $namespace = 'App\\Models\\';
            } else {
                $module_name = basename($m);
                $namespace = sprintf('%s\\%s\\%s\\', config('modules.namespace'), $module_name, Str::replace(['app/', '/'], ['', '\\'], $modules_models_folder));
            }

            foreach ($model_files as $model_file) {
                if ($model_file->getExtension() !== 'php') {
                    continue;
                }
                $class_subnamespace = $namespace . preg_replace(['/\.' . $model_file->getExtension() . '/', '/\//'], ['', '\\'], $model_file->getRelativePathName());

                if (! is_subclass_of($class_subnamespace, Model::class)) {
                    continue;
                }

                if (new ReflectionClass($class_subnamespace)->isAbstract()) {
                    continue;
                }
                $models[] = $class_subnamespace;
            }
        }

        return $models;
    }
}

if (! function_exists('controllers')) {
    /**
     * list all Controllers.
     *
     * @param  bool  $onlyActive  filter for only active modules
     * @param  null|string  $onlyModule  filter for specified module
     * @return array<int,string>
     *
     * @psalm-return list{0?: string,...}
     */
    function controllers(bool $onlyActive = true, ?string $onlyModule = null): array
    {
        $modules_controllers_folder = config('modules.paths.generator.controller.path');
        $modules = modules(true, true, $onlyActive, $onlyModule);
        $controllers = [];

        foreach ($modules as $m) {
            $is_app = (bool) preg_match("/[\\\\\/]app$/", $m);

            $controllers_path = $m . DIRECTORY_SEPARATOR . ($is_app ? 'Http' . DIRECTORY_SEPARATOR . 'Controllers' : $modules_controllers_folder);
            $controllers_files = File::allFiles($controllers_path);

            if ($is_app) {
                $namespace = 'App\\Http\\Controllers\\';
            } else {
                $module_name = basename($m);
                $namespace = sprintf('%s\\%s\\%s\\', config('modules.namespace'), $module_name, str_replace(DIRECTORY_SEPARATOR, '\\', $modules_controllers_folder));
            }

            foreach ($controllers_files as $controller_file) {
                $name = $controller_file->getFilenameWithoutExtension();

                if ($name !== 'Controller' && ! Str::contains($name, 'Abstract')) {
                    $controllers[] = $namespace . $name;
                }
            }
        }

        return $controllers;
    }
}

if (! function_exists('routes')) {
    /**
     * list all Controllers.
     *
     * @param  bool  $onlyActive  filter for only active modules
     * @param  null|string  $onlyModule  filter for specified module
     * @return array<int,Route>
     *
     * @psalm-return list{0?: string,...}
     */
    function routes(bool $onlyActive = true, ?string $onlyModule = null): array
    {
        /** @var array<int,Route> $routes */
        $routes = [];
        $modules = modules(true, false, $onlyActive, $onlyModule);
        $all_routes = app('router')->getRoutes()->getRoutes();
        usort($all_routes, fn (Route $a, Route $b): int => $a->uri() <=> $b->uri());

        foreach ($all_routes as $route) {
            $reference = $route->action['namespace'] ?? $route->action['controller'] ?? $route->action['uses'];

            if (is_callable($reference)) {
                $r = new ReflectionFunction($reference);
                $reference = $r->getName();
            }
            $exploded = explode('\\', (string) $reference);

            if (($exploded[0] !== 'Modules' && ($onlyModule === null || $onlyModule === '' || $onlyModule === '0' || $onlyModule === 'App')) || (in_array($exploded[1], $modules, true) && ($onlyModule === null || $onlyModule === '' || $onlyModule === '0' || $exploded[1] === $onlyModule))) {
                $routes[] = $route;
            }
        }

        return $routes;
    }
}

if (! function_exists('version')) {
    /**
     * Return App Version.
     */
    function version(): string
    {
        $json = file_get_contents(base_path('composer.json'));

        return json_decode($json, true)['version'] ?? '';
    }
}

if (! function_exists('api_versions')) {
    /**
     * Return Api Versions.
     *
     * @return array<int,string>
     *
     * @psalm-return list<string>
     */
    function api_versions(): array
    {
        $routes = app('router')->getRoutes()->getRoutes();
        $versions = [];

        foreach ($routes as $route) {
            $uri = $route->uri;
            $matches = [];
            preg_match("/^api\/(v\d+)\//", (string) $uri, $matches);

            if (count($matches) === 2 && ! in_array($matches[1], $versions, true)) {
                $versions[] = $matches[1];
            }
        }

        return $versions;
    }
}

if (! function_exists('preview')) {
    /**
     * Getter/Setter for session preview flag.
     *
     * @param  null|bool  $enablePreview  enable preview flag
     */
    function preview(?bool $enablePreview = null): bool
    {
        if ($enablePreview !== null) {
            session()->put('preview', $enablePreview);
        }

        return (bool) session('preview', false);
    }
}

if (! function_exists('class_uses_trait')) {
    /**
     * Check if a class uses a trait.
     *
     * @param  string|object  $class  The class to check
     * @param  string  $uses  The trait to check for
     */
    function class_uses_trait(string|object $class, string $uses): bool
    {
        return in_array($uses, class_uses_recursive(is_string($class) ? $class : $class::class), true);
    }
}

if (! function_exists('is_json')) {
    /**
     * Check if a string is a valid JSON.
     *
     * @param  string  $string  The string to check
     */
    function is_json(string $string): bool
    {
        /** @psalm-suppress UnusedFunctionCall */
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}

if (! function_exists('array_sort_keys')) {
    /**
     * Sort the keys of an array.
     *
     * @param  array  $array  l'array non deve avere chiavi numeriche
     */
    function array_sort_keys(array $array): array
    {
        $keys = array_keys($array);
        sort($keys);
        $result = [];

        foreach ($keys as $key) {
            $value = $array[(string) $key];

            $result[(string) $key] = is_array($value) ? array_sort_keys($value) : $value;
        }

        return $result;
    }
}

if (! function_exists('user_class')) {
    /**
     * Get the user model class.
     *
     * @return class-string
     */
    function user_class(): string
    {
        return config('auth.providers.users.model');
    }
}

if (! function_exists('cast_value')) {
    /**
     * Cast a value to a specific type.
     *
     * @param  mixed  $value  The value to cast
     * @param  null|string  $type  The type to cast to
     */
    function cast_value(mixed $value, ?string $type = null): mixed
    {
        if ($type !== null && $type !== '' && $type !== '0') {
            return match (mb_strtolower($type)) {
                'int', 'integer' => (int) $value,
                'float', 'double', 'real' => (float) $value,
                'string' => (string) $value,
                'bool', 'boolean' => (bool) $value,
                'array' => (array) $value,
                'object' => (object) $value,
                'null' => null,
                default => throw new InvalidArgumentException("Unsupported type: {$type}"),
            };
        }

        if ($value === null || $value === 'null') {
            return null;
        }

        if (is_numeric($value)) {
            // Check if the value is an integer
            if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
                return (int) $value;
            }

            // Otherwise treat as float
            return (float) $value;
        }

        if (mb_strtolower((string) $value) === 'true') {
            return true;
        }

        if (mb_strtolower((string) $value) === 'false') {
            return false;
        }

        // JSON array or object
        if ((mb_substr((string) $value, 0, 1) === '[' && mb_substr((string) $value, -1) === ']')
            || (mb_substr((string) $value, 0, 1) === '{' && mb_substr((string) $value, -1) === '}')
        ) {
            $decoded = json_decode((string) $value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Default to string
        return (string) $value;
    }
}

if (! function_exists('class_module')) {
    /**
     * Get the module of a class.
     *
     * @param  string  $class  The class to get the module of
     */
    function class_module(string $class): ?string
    {
        if (! class_exists($class)) {
            return null;
        }

        return Str::startsWith($class, 'Modules\\') ? Str::before(Str::after($class, 'Modules\\'), '\\') : null;
    }
}
