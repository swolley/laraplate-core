<?php

declare(strict_types=1);

namespace Modules\Core\Filament;

/**
 * Resolves which HasTable / HasRecords / HasForm trait FQCN to use for generated Filament classes.
 */
final class FilamentTraitResolver
{
    /**
     * @param  'HasTable'|'HasRecords'|'HasForm'  $trait
     * @return class-string
     */
    public static function resolve(string $generatedClassFqn, string $trait): string
    {
        $module = self::moduleFromFqn($generatedClassFqn);

        if ($module !== null) {
            $candidate = sprintf('Modules\\%s\\Filament\\Utils\\%s', $module, $trait);

            if (trait_exists($candidate)) {
                return $candidate;
            }
        }

        return sprintf('Modules\\Core\\Filament\\Utils\\%s', $trait);
    }

    public static function moduleFromFqn(string $fqn): ?string
    {
        if (str_starts_with($fqn, 'App\\')) {
            return null;
        }

        if (preg_match('/^Modules\\\\([^\\\\]+)\\\\/', $fqn, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }
}
