<?php

declare(strict_types=1);

namespace Modules\Core\Filament;

use Modules\Core\Models\Concerns\HasDynamicContents;

/**
 * Resolves which HasTable / HasRecords / HasForm trait FQCN to use for generated Filament classes.
 */
final class FilamentTraitResolver
{
    /**
     * DB columns owned by HasForm for HasDynamicContents models.
     * Filament `--generate` must exclude these so they are not duplicated beside the Entity→Preset cascade.
     *
     * @var list<string>
     */
    public const HAS_DYNAMIC_CONTENTS_OWNED_FORM_COLUMNS = [
        'entity_id',
        'presettable_id',
    ];

    /**
     * Filament `--generate` column names replaced by HasTable composites / platform columns.
     * Applied at generate-time (exceptColumns) and again at runtime merge.
     *
     * @var list<string>
     */
    public const HAS_TABLE_STRIP_FROM_GENERATED_COLUMNS = [
        'created_at',
        'updated_at',
        'deleted_at',
        'valid_from',
        'valid_to',
    ];

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

    /**
     * Columns to pass as Filament form `exceptColumns` for the given model.
     *
     * @param  class-string  $modelFqn
     * @return list<string>
     */
    public static function formColumnsOwnedByHasForm(string $modelFqn): array
    {
        if (! class_exists($modelFqn) || ! class_uses_trait($modelFqn, HasDynamicContents::class)) {
            return [];
        }

        return self::HAS_DYNAMIC_CONTENTS_OWNED_FORM_COLUMNS;
    }

    /**
     * Columns HasTable owns / replaces — exclude from Filament table `--generate` and strip at runtime.
     *
     * @param  class-string  $modelFqn
     * @return list<string>
     */
    public static function tableColumnsOwnedByHasTable(string $modelFqn): array
    {
        $owned = self::HAS_TABLE_STRIP_FROM_GENERATED_COLUMNS;

        if (class_exists($modelFqn) && method_exists($modelFqn, 'activationColumn')) {
            /** @var string $activation */
            $activation = $modelFqn::activationColumn();
            $owned[] = $activation;
        }

        return array_values(array_unique($owned));
    }
}
