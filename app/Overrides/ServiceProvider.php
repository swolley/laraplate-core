<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Override;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    protected string $name;

    protected string $nameLower;

    protected function registerConfig(): void
    {
        $relativeConfigPath = config('modules.paths.generator.config.path');
        $configPath = module_path($this->name, $relativeConfigPath);

        if (is_dir($configPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relativePath = str_replace($configPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $configKey = str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $relativePath);
                    $key = $relativePath === 'config.php' ? $this->nameLower : $configKey;

                    $this->publishes([$file->getPathname() => config_path($relativePath)], $configPath);
                    $this->mergeConfigFrom($file->getPathname(), $key);
                }
            }
        }
    }

    #[Override]
    protected function mergeConfigFrom($path, $key): void
    {
        if (! ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached())) {
            $config = $this->app->make('config');

            $original = $config->get($key, []);
            $current = require $path;
            $merged = self::mergeArrays($original, $current);
            $config->set($key, $merged);
        }
    }

    private static function mergeArrays(array &$array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (! array_key_exists($key, $array1)) {
                $array1[$key] = $value;
            } elseif (is_array($value)) {
                // if (!isset($array1[$key])) {
                // 	$array1[$key] = [];
                // }
                $array1[$key] = self::mergeArrays($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }

        return $array1;
    }
}
