<?php

declare(strict_types=1);

namespace Modules\Core\Models\Pivot;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Modules\Core\SoftDeletes\SoftDeletes;
use Modules\Core\Models\Entity;
use Modules\Core\Models\Field;
use Modules\Core\Models\Preset;
use Override;

/**
 * @property int $version
 * @property array<int, array{field_id: int, name: string, type: string, options: mixed, is_translatable: bool, is_slug: bool, pivot: array{is_required: bool, order_column: int, default: mixed}}> $fields_snapshot
 *
 * @mixin IdeHelperPresettable
 */
abstract class Presettable extends Pivot
{
    use HasFactory, SoftDeletes;

    #[Override]
    final public $incrementing = true;

    #[Override]
    final public $timestamps = false;

    #[Override]
    final protected $table = 'presettables';

    #[Override]
    final protected $fillable = [
        'preset_id',
        'entity_id',
        'fields_snapshot',
    ];

    #[Override]
    final protected $with = [
        'preset',
        'entity',
    ];

    /**
     * @return BelongsTo<Preset>
     */
    abstract public function preset(): BelongsTo;

    /**
     * @return BelongsTo<Entity>
     */
    abstract public function entity(): BelongsTo;

    /**
     * Hydrate Field model instances from the frozen snapshot.
     * Each field gets a Fieldable pivot attached, so downstream code
     * that accesses $field->pivot->is_required etc. works unchanged.
     *
     * @return Collection<int, Field>
     */
    final public function getFieldsFromSnapshot(): Collection
    {
        $fields = collect($this->fields_snapshot ?? [])
            ->map(static function (array $data): Field {
                $field = new Field();
                $field->forceFill([
                    'id' => $data['field_id'],
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'options' => $data['options'],
                    'is_translatable' => $data['is_translatable'],
                    'is_slug' => $data['is_slug'],
                ]);
                $field->exists = true;
                $field->syncOriginal();

                $pivot = new Fieldable();
                $pivot->forceFill([
                    'is_required' => $data['pivot']['is_required'],
                    'order_column' => $data['pivot']['order_column'],
                    'default' => $data['pivot']['default'] ?? null,
                ]);
                $pivot->exists = true;
                $pivot->syncOriginal();

                $field->setRelation('pivot', $pivot);

                return $field;
            })
            ->sortBy(fn (Field $field): int => $field->getRelation('pivot')->order_column)
            ->values();

        return new Collection($fields->all());
    }

    final protected static function boot(): void
    {
        parent::boot();

        self::creating(static function (self $presettable): void {
            $presettable->version = (int) self::query()
                ->withTrashed()
                ->where('preset_id', $presettable->preset_id)
                ->where('entity_id', $presettable->entity_id)
                ->max('version') + 1;
        });
    }

    final protected function casts(): array
    {
        return [
            'version' => 'integer',
            'fields_snapshot' => 'json',
            'created_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }
}
