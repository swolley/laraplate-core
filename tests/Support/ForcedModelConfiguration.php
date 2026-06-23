<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Concerns\HasApprovals;
use Modules\Core\Models\Concerns\HasTranslations;
use Modules\Core\Models\Concerns\HasVersions;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Locking\Traits\HasOptimisticLocking;
use Modules\Core\SoftDeletes\SoftDeletes;
use Overtrue\LaravelVersionable\VersionStrategy;
use ReflectionClass;

/**
 * Discovers Eloquent models that pin Core feature flags in the class body (not via Settings).
 */
final class ForcedModelConfiguration
{
    /**
     * @return list<array{
     *     model: class-string<Model>,
     *     property: string,
     *     expected: mixed,
     *     settingName: string,
     *     groupName: string,
     * }>
     */
    public static function cases(): array
    {
        $cases = [];

        foreach (models(onlyActive: false) as $model_class) {
            $reflection = new ReflectionClass($model_class);

            foreach (self::propertyDefinitions() as $property => $definition) {
                if (! $reflection->hasProperty($property)) {
                    continue;
                }

                $property_reflection = $reflection->getProperty($property);

                if ($property_reflection->getDeclaringClass()->getName() !== $model_class) {
                    continue;
                }

                if (! $property_reflection->hasDefaultValue()) {
                    continue;
                }

                if (! self::modelUsesCapability($model_class, $definition['capability_trait'])) {
                    continue;
                }

                $instance = $reflection->newInstanceWithoutConstructor();
                $table = $instance->getTable();

                $cases[] = [
                    'model' => $model_class,
                    'property' => $property,
                    'expected' => $property_reflection->getDefaultValue(),
                    'settingName' => "{$definition['prefix']}_{$table}",
                    'groupName' => $definition['group'],
                ];
            }
        }

        return $cases;
    }

    public static function normalizeExpected(mixed $expected): mixed
    {
        if ($expected instanceof VersionStrategy) {
            return $expected;
        }

        return $expected;
    }

    /**
     * Read the value declared on the model class (handles private properties on subclasses).
     */
    public static function readDeclaredPropertyValue(object $instance, string $property): mixed
    {
        $property_reflection = new ReflectionClass($instance)->getProperty($property);

        if ($property_reflection->isInitialized($instance)) {
            return $property_reflection->getValue($instance);
        }

        if ($property_reflection->hasDefaultValue()) {
            return $property_reflection->getDefaultValue();
        }

        throw new \RuntimeException("Property [{$property}] on " . $instance::class . ' has no default value.');
    }

    /**
     * @return array<string, array{prefix: string, group: string, capability_trait: class-string}>
     */
    private static function propertyDefinitions(): array
    {
        return [
            'softDeletesEnabled' => [
                'prefix' => CoreDatabaseSeeder::SOFT_DELETES_NAME_PREFIX,
                'group' => 'soft_deletes',
                'capability_trait' => SoftDeletes::class,
            ],
            'versionStrategy' => [
                'prefix' => CoreDatabaseSeeder::VERSIONING_NAME_PREFIX,
                'group' => 'versioning',
                'capability_trait' => HasVersions::class,
            ],
            'locksEnabled' => [
                'prefix' => CoreDatabaseSeeder::LOCK_NAME_PREFIX,
                'group' => 'locking',
                'capability_trait' => HasLocks::class,
            ],
            'optimisticLocksEnabled' => [
                'prefix' => CoreDatabaseSeeder::OPTIMISTIC_LOCK_NAME_PREFIX,
                'group' => 'locking',
                'capability_trait' => HasOptimisticLocking::class,
            ],
            'translation_fallback_enabled' => [
                'prefix' => CoreDatabaseSeeder::TRANSLATION_FALLBACK_NAME_PREFIX,
                'group' => 'translations',
                'capability_trait' => HasTranslations::class,
            ],
            'auto_translate_enabled' => [
                'prefix' => CoreDatabaseSeeder::AUTO_TRANSLATE_NAME_PREFIX,
                'group' => 'translations',
                'capability_trait' => HasTranslations::class,
            ],
            'ai_moderation_enabled' => [
                'prefix' => CoreDatabaseSeeder::AI_MODERATION_NAME_PREFIX,
                'group' => 'moderation',
                'capability_trait' => HasApprovals::class,
            ],
        ];
    }

    /**
     * @param  class-string<Model>  $model_class
     * @param  class-string  $trait
     */
    private static function modelUsesCapability(string $model_class, string $trait): bool
    {
        return in_array($trait, class_uses_recursive($model_class), true);
    }

    public static function classSourceDeclaresDefault(ReflectionClass $class, string $property_name, mixed $expected): bool
    {
        $source = file_get_contents($class->getFileName());

        if ($source === false) {
            return false;
        }

        $needle = match (true) {
            is_bool($expected) => '$' . $property_name . ' = ' . ($expected ? 'true' : 'false'),
            $expected instanceof VersionStrategy => '$' . $property_name . ' = VersionStrategy::' . $expected->name,
            default => null,
        };

        if ($needle === null) {
            return false;
        }

        return str_contains($source, $needle);
    }
}
