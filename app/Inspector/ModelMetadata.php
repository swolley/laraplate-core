<?php

declare(strict_types=1);

namespace Modules\Core\Inspector;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes as BaseSoftDeletes;
use Modules\Core\Cache\HasCache;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Helpers\HasActivation;
use Modules\Core\Helpers\HasTranslations;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Helpers\SoftDeletes;
use Modules\Core\Helpers\SortableTrait;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Search\Traits\Searchable;
use ReflectionClass;
use Spatie\EloquentSortable\SortableTrait as BaseSortableTrait;

/**
 * Immutable snapshot of a Model class's static metadata.
 * Built once per class and cached by ModelMetadataRegistry.
 */
final readonly class ModelMetadata
{
    /**
     * @param  class-string<Model>  $class
     * @param  array<int, string>  $traits
     * @param  array<int, string>  $fillable
     * @param  array<int, string>  $hidden
     * @param  array<string, string>  $casts
     * @param  string|array<int, string>  $keyName
     */
    public function __construct(
        public string $class,
        public string $table,
        public ?string $connection,
        public array $traits,
        public array $fillable,
        public array $hidden,
        public array $casts,
        public bool $timestamps,
        public bool $incrementing,
        public string|array $keyName,
        public string $keyType,
        public bool $hasSoftDeletes,
        public bool $hasValidity,
        public bool $hasActivation,
        public bool $hasLocks,
        public bool $hasSorts,
        public bool $hasSearchable,
        public bool $hasTranslations,
        public bool $hasGridUtils,
        public bool $hasCache,
    ) {}

    /**
     * @param  class-string<Model>  $class
     */
    public static function fromClass(string $class): self
    {
        $instance = new ReflectionClass($class)->newInstanceWithoutConstructor();
        $traits = class_uses_recursive($class);

        return new self(
            class: $class,
            table: $instance->getTable(),
            connection: $instance->getConnectionName(),
            traits: array_values($traits),
            fillable: $instance->getFillable(),
            hidden: $instance->getHidden(),
            casts: $instance->getCasts(),
            timestamps: $instance->usesTimestamps(),
            incrementing: $instance->getIncrementing(),
            keyName: $instance->getKeyName(),
            keyType: $instance->getKeyType(),
            hasSoftDeletes: (isset($traits[SoftDeletes::class]) && $instance->softDeletesEnabledBySettings()) || isset($traits[BaseSoftDeletes::class]),
            hasValidity: isset($traits[HasValidity::class]),
            hasActivation: isset($traits[HasActivation::class]),
            hasLocks: isset($traits[HasLocks::class]),
            hasSorts: isset($traits[SortableTrait::class]) || isset($traits[BaseSortableTrait::class]),
            hasSearchable: isset($traits[Searchable::class]),
            hasTranslations: isset($traits[HasTranslations::class]),
            hasGridUtils: isset($traits[HasGridUtils::class]),
            hasCache: isset($traits[HasCache::class]),
        );
    }

    public function hasTrait(string $trait): bool
    {
        return in_array($trait, $this->traits, true);
    }
}
