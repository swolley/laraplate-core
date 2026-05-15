<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Nwidart\Modules\Facades\Module;

/**
 * Resolves the owning module for a migration file path or migration name.
 */
final class MigrationModuleResolver
{
    public static function resolveFromPath(string $path): string
    {
        return file_module(realpath($path) ?: $path);
    }

    public static function resolveFromName(string $migration_name): string
    {
        if (is_file(database_path('migrations' . DIRECTORY_SEPARATOR . $migration_name . '.php'))) {
            return 'App';
        }

        if (! class_exists(Module::class)) {
            return 'App';
        }

        try {
            foreach (Module::all() as $module) {
                $file = module_path($module->getName(), 'database/migrations/' . $migration_name . '.php');

                if (is_file($file)) {
                    return file_module($file);
                }
            }
        } catch (\Throwable) {
            return 'App';
        }

        return 'App';
    }
}
