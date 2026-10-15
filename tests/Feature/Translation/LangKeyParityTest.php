<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

/**
 * Every locale of a module's lang directory carries the same keys, and keys that name an attribute or an
 * entity follow the database names. The Filament backoffice and the Vue UI both read these files, so a key
 * missing in one locale or spelled differently from the column it labels shows up as raw text on a screen.
 *
 * Roots are resolved from this file rather than base_path(): datasets are built before the application boots.
 *
 * @return array<string, array{string}>
 */
function langRoots(): array
{
    $roots = glob(dirname(__DIR__, 5) . '/Modules/*/lang', GLOB_ONLYDIR) ?: [];

    return array_combine(
        array_map(static fn (string $root): string => basename(dirname($root)), $roots),
        array_map(static fn (string $root): array => [$root], $roots),
    );
}

/**
 * @return array<string, array<string, list<string>>> group => locale => dotted keys
 */
function langKeysByGroup(string $root): array
{
    $keys = [];

    foreach (glob($root . '/*/*.php') ?: [] as $file) {
        $locale = basename(dirname($file));
        $group = basename($file, '.php');
        $lines = require $file;

        $keys[$group][$locale] = is_array($lines) ? array_keys(Arr::dot($lines)) : [];
    }

    return $keys;
}

it('has the same keys in every locale', function (string $root): void {
    $missing = [];
    $locale_dirs = array_map('basename', glob($root . '/*', GLOB_ONLYDIR) ?: []);

    foreach (langKeysByGroup($root) as $group => $locales) {
        $all = array_unique(array_merge(...array_values($locales)));

        foreach ($locale_dirs as $locale) {
            foreach (array_diff($all, $locales[$locale] ?? []) as $key) {
                $missing[] = "{$locale}/{$group}.php: {$key}";
            }
        }
    }

    expect($missing)->toBe([]);
})->with(langRoots());

it('names attribute and entity keys like the database', function (string $root): void {
    $misnamed = [];

    foreach (langKeysByGroup($root) as $group => $locales) {
        foreach ($locales['en'] ?? [] as $key) {
            $segments = explode('.', $key);

            if (! in_array($segments[0], ['fields', 'entities'], true)) {
                continue;
            }

            foreach (array_slice($segments, 1) as $segment) {
                if (preg_match('/^[a-z][a-z0-9_]*$/', $segment) !== 1) {
                    $misnamed[] = "{$group}.{$key}";

                    break;
                }
            }
        }
    }

    expect($misnamed)->toBe([]);
})->with(langRoots());
