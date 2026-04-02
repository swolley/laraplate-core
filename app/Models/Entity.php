<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Cache\HasCache;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Database\Factories\EntityFactory;
use Modules\Core\Helpers\HasActivation;
use Modules\Core\Helpers\HasPath;
use Modules\Core\Helpers\HasSlug;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Locking\Traits\HasLocks;
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
    use HasFactory;
    use HasLocks;
    use HasPath;
    use HasSlug;
    use HasValidations {
        getRules as private getRulesTrait;
    }
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
     * The presets that belong to the entity.
     *
     * @return HasMany<Preset>
     */
    final public function presets(): HasMany
    {
        return $this->hasMany(Preset::class);
    }

    public function getRules(): array
    {
        $rules = $this->getRulesTrait();
        $rules[self::DEFAULT_RULE] = array_merge($rules[self::DEFAULT_RULE], [
            'is_active' => 'boolean',
            'slug' => 'sometimes|nullable|string|max:255',
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

    protected static function newFactory(): EntityFactory
    {
        return EntityFactory::new();
    }

    #[Override]
    protected static function booted(): void
    {
        self::addGlobalScope('active', static function (Builder $builder): void {
            $builder->active();
        });
    }

    protected function casts(): array
    {
        return array_merge($this->activationCasts(), [
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ]);
    }
}
