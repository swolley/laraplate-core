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
        'is_deleted',
        'valid_from',
        'valid_to',
    ];

    /**
     * Platform columns that must never surface as ordinary form fields.
     *
     * Filament's `--generate` skips the timestamp columns it knows by Laravel
     * convention, but `is_deleted` is added by MigrateUtils alongside
     * `deleted_at` and the lock version column is added for optimistic locking —
     * neither is a convention Filament can infer, so both would be emitted as
     * editable fields. HasForm already injects the lock version as a hidden
     * component, so generating it again would duplicate it.
     *
     * @var list<string>
     */
    public const PLATFORM_COLUMNS_NEVER_IN_FORMS = [
        'is_deleted',
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
     * Platform columns that must never be generated as ordinary form fields.
     *
     * Separate from the HasForm-owned list because these are not owned by
     * HasForm at all: `is_deleted` belongs to the soft-delete machinery and the
     * lock version to optimistic locking. HasForm already injects the latter as
     * a hidden component, so generating it again would duplicate it.
     *
     * @param  class-string  $modelFqn
     * @return list<string>
     */
    public static function platformColumnsNeverInForms(string $modelFqn): array
    {
        $excluded = self::PLATFORM_COLUMNS_NEVER_IN_FORMS;

        if (class_exists($modelFqn) && method_exists($modelFqn, 'lockVersionColumn')) {
            /** @var string $lock_version */
            $lock_version = $modelFqn::lockVersionColumn();
            $excluded[] = $lock_version;
        }

        return array_values(array_unique($excluded));
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
