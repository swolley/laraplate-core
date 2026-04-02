<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Modules\Core\Cache\HasCache;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Helpers\HasActivation;
use Modules\Core\Helpers\HasApprovals;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Helpers\SoftDeletes;
use Modules\Core\Models\Pivot\Fieldable;
use Modules\Core\Models\Pivot\Presettable;
use Modules\Core\Services\PresetVersioningService;
use Override;

/**
 * @property int|string $id
 * @property string $name
 * @property int|string|null $entity_id
 * @property int|string|null $template_id
 */
final class Preset extends Model
{
    use HasActivation {
        HasActivation::casts as private activationCasts;
    }
    use HasApprovals;
    use HasCache;
    use HasFactory;
    use HasValidations {
        getRules as private getRulesTrait;
    }
    use HasVersions;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'entity_id',
        'name',
        'template_id',
    ];

    #[Override]
    protected $hidden = [
        'entity_id',
        'template_id',
        'created_at',
        'updated_at',
    ];

    /**
     * @return BelongsTo<Template>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * @return BelongsTo<Entity>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * @return HasManyThrough<Content>
     */
    public function contents(): HasManyThrough
    {
        return $this->hasManyThrough(
            Content::class,
            Presettable::class,
            'preset_id',      // foreign key on presettables pointing to presets
            'presettable_id', // foreign key on contents pointing to presettables
        );
    }

    /**
     * @return BelongsToMany<Field,Preset,Fieldable,'pivot'>
     */
    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(Field::class, 'fieldables')->using(Fieldable::class)->withTimestamps()->withPivot(['id', 'order_column', 'is_required', 'default']);
    }

    /**
     * Create a new presettable version with the current fields snapshot.
     * Call this after modifying the fields relationship (attach/detach/sync)
     * since BelongsToMany bulk operations don't fire pivot model events.
     */
    public function createFieldsVersion(): Presettable
    {
        return resolve(PresetVersioningService::class)->createVersion($this);
    }

    /**
     * Get the current active presettable for this preset.
     */
    public function activePresettable(): ?Presettable
    {
        return Presettable::query()
            ->where('preset_id', $this->id)
            ->where('entity_id', $this->entity_id)
            ->whereNull('deleted_at')
            ->latest('version')
            ->first();
    }

    public function getRules(): array
    {
        $rules = $this->getRulesTrait();
        $rules[self::DEFAULT_RULE] = array_merge($rules[self::DEFAULT_RULE], [
            'is_active' => 'boolean',
            'template_id' => ['sometimes', 'exists:templates,id'],
            'entity_id' => ['required', 'exists:entities,id'],
        ]);
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255'],
        ]);

        return $rules;
    }

    /**
     * Migrate all contents to the latest presettable version.
     * This reassigns every content's presettable_id to the current active version.
     */
    public function migrateContentsToLastVersion(): void
    {
        $active = $this->activePresettable();

        if (! $active instanceof Presettable) {
            return;
        }

        Content::query()
            ->whereIn('presettable_id', function (QueryBuilder $query): void {
                $query->select('id')
                    ->from('presettables')
                    ->where('preset_id', $this->id)
                    ->where('entity_id', $this->entity_id);
            })
            ->update(['presettable_id' => $active->id]);
    }

    /**
     * Active presets whose related entity is active and matches the given CMS table type (e.g. contents).
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function forActiveEntityOfType(Builder $query, IDynamicEntityTypable $entity_type): void
    {
        $query->where($query->qualifyColumn(self::activationColumn()), true)
            ->whereHas('entity', function (Builder $entity_query) use ($entity_type): void {
                $entity_query->where($entity_query->qualifyColumn(Entity::activationColumn()), true)
                    ->where($entity_query->qualifyColumn('type'), $entity_type);
            });
    }

    protected function casts(): array
    {
        return array_merge($this->activationCasts(), [
            'template_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ]);
    }
}
