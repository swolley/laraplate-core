<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Cache\HasCache;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Database\Factories\EntityFactory;
use Modules\Core\Helpers\HasActivation;
use Modules\Core\Helpers\HasPath;
use Modules\Core\Helpers\HasSlug;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Overrides\Model;
use Override;

/**
 * @property int|string $id
 * @property string $name
 * @property string $slug
 * @property IDynamicEntityTypable $type
 */
abstract class Entity extends Model
{
    // region Traits
    use HasActivation {
        HasActivation::casts as private activationCasts;
    }
    use HasCache;
    use HasLocks;
    use HasPath;
    use HasSlug;
    // endregion

    #[Override]
    final protected $table = 'entities';

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    final protected $fillable = [
        'name',
        'slug',
        'type',
    ];

    #[Override]
    final protected $hidden = [
        'created_at',
        'updated_at',
        'type',
    ];

    /**
     * Get the entity type enum class.
     *
     * @return class-string<IDynamicEntityTypable>
     */
    abstract protected static function getEntityTypeEnumClass(): string;

    /**
     * The presets that belong to the entity.
     *
     * @return HasMany<Preset>
     */
    final public function presets(): HasMany
    {
        return $this->hasMany(str_replace('Entity', 'Preset', static::class));
    }

    final public function getRules(): array
    {
        $rules = parent::getRules();
        $rules[Model::DEFAULT_RULE] = array_merge($rules[Model::DEFAULT_RULE], [
            'is_active' => 'boolean',
            'slug' => 'sometimes|nullable|string|max:255',
            'type' => ['required', static::getEntityTypeEnumClass()::validationRule()],
        ]);
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255', 'unique:entities,name'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255', 'unique:entities,name,' . $this->id],
        ]);

        return $rules;
    }

    #[Override]
    public function getPath(): ?string
    {
        return null;
    }

    #[Override]
    protected function newBaseQueryBuilder(): QueryBuilder
    {
        return parent::newBaseQueryBuilder()->whereIn($this->qualifyColumn('type'), static::getEntityTypeEnumClass()::values());
    }

    protected static function newFactory(): EntityFactory
    {
        $factory = EntityFactory::new();
        $factory->model = static::class;

        return $factory;
    }

    #[Override]
    protected static function booted(): void
    {
        self::addGlobalScope('active', static function (Builder $builder): void {
            $builder->active();
        });
    }

    final protected function casts(): array
    {
        return array_merge($this->activationCasts(), [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
            'type' => static::getEntityTypeEnumClass(),
        ]);
    }
}
