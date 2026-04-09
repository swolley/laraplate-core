<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Modules\Core\Casts\FieldType;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Models\Entity;
use Modules\Core\Models\Field;
use Modules\Core\Models\Pivot\Presettable;
use Modules\Core\Models\Preset;
use Modules\Core\Services\DynamicContentsService;
use Override;
use stdClass;

/**
 * Trait for models that have dynamic contents based on presets.
 *
 * Architecture:
 * - Dynamic fields are determined from the preset's fields configuration
 * - Field values are stored in a JSON `components` column
 * - Individual dynamic fields can be accessed transparently (e.g., $model->public_email)
 * - Custom accessors/mutators are supported for both components and individual fields
 *
 * Usage:
 * - Add trait to model: use HasDynamicContents;
 * - Model must have: components (JSON), entity_id, presettable_id columns
 * - Access fields: $model->fieldName or $model->components['fieldName']
 *
 * @property array<string, mixed> $components
 * @property-read ?string $type
 * @property-read ?Entity $entity
 * @property-read ?Preset $preset
 * @property ?int $entity_id
 * @property ?int $presettable_id
 *
 * @template TModel of Model
 */
trait HasDynamicContents
{
    abstract public static function getEntityType(): IDynamicEntityTypable;

    abstract public static function getEntityModelClass(): string;

    /**
     * Fetch available entities for a given type.
     *
     * @return Collection<int,Entity>
     */
    public static function fetchAvailableEntities(IDynamicEntityTypable $type): Collection
    {
        return DynamicContentsService::getInstance()->fetchAvailableEntities($type);
    }

    /**
     * Fetch available presets for a given entity type.
     *
     * @return Collection<int,Preset>
     */
    public static function fetchAvailablePresets(IDynamicEntityTypable $type): Collection
    {
        return DynamicContentsService::getInstance()->fetchAvailablePresets($type);
    }

    /**
     * Fetch available presettables for a given entity type.
     *
     * @return Collection<int,Presettable>
     */
    public static function fetchAvailablePresettables(IDynamicEntityTypable $type): Collection
    {
        return DynamicContentsService::getInstance()->fetchAvailablePresettables($type);
    }

    /**
     * Override getAttribute to handle dynamic fields.
     *
     * Priority:
     * 1. Standard Eloquent attributes (delegate to parent first)
     * 2. If dynamic field → get from components
     * 3. Otherwise → delegate to parent
     *
     * @param  string  $key
     */
    public function getAttribute($key): mixed
    {
        // Let Eloquent handle standard attributes, relations, and accessors first
        // Check if it's in attributes, has a cast, or has an accessor
        if (
            array_key_exists($key, $this->attributes)
            || $this->hasGetMutator($key)
            || $this->hasAttributeMutator($key)
            || method_exists($this, $key)
            || $key === 'pivot'
        ) {
            return parent::getAttribute($key);
        }

        // Handle dynamic fields from preset
        if ($this->isDynamicField($key)) {
            return data_get($this->getComponentsAttribute(), $key);
        }

        return parent::getAttribute($key);
    }

    /**
     * Override setAttribute to handle dynamic fields.
     *
     * Priority:
     * 1. If dynamic field → store in components
     * 2. Otherwise → delegate to parent
     * 3. Special handling for presettable_id to sync entity_id
     *
     * @param  string  $key
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if ($this->isDynamicField($key)) {
            $this->setComponentAttribute($key, $value);

            return $this;
        }

        $result = parent::setAttribute($key, $value);

        // Sync entity_id when presettable_id changes
        if ($key === 'presettable_id' && $value) {
            $this->entity_id = $this->presettable?->entity_id;
        }

        return $result;
    }

    public function initializeHasDynamicContents(): void
    {
        if (! in_array('components', $this->hidden, true)) {
            $this->hidden[] = 'components';
        }

        if (! in_array('shared_components', $this->hidden, true)) {
            $this->hidden[] = 'shared_components';
        }

        if (! in_array('components', $this->fillable, true)) {
            $this->fillable[] = 'components';
        }

        if (! in_array('shared_components', $this->fillable, true)) {
            $this->fillable[] = 'shared_components';
        }

        if (! in_array('entity_id', $this->fillable, true)) {
            $this->fillable[] = 'entity_id';
        }

        if (! in_array('presettable_id', $this->fillable, true)) {
            $this->fillable[] = 'presettable_id';
        }

        if (! isset($this->attributes['components'])) {
            $this->attributes['components'] = '{}';
        }

        if (! isset($this->attributes['shared_components'])) {
            $this->attributes['shared_components'] = '{}';
        }

        if (! in_array('type', $this->appends, true)) {
            $this->appends[] = 'type';
        }

        if (! in_array('entity_id', $this->hidden, true)) {
            $this->hidden[] = 'entity_id';
        }

        if (! in_array('presettable_id', $this->hidden, true)) {
            $this->hidden[] = 'presettable_id';
        }

        if (! in_array('preset', $this->hidden, true)) {
            $this->hidden[] = 'preset';
        }

        if (! in_array('entity', $this->hidden, true)) {
            $this->hidden[] = 'entity';
        }

        if (! in_array('presettable', $this->with, true)) {
            $this->with[] = 'presettable';
        }

        if (! in_array('presettable', $this->hidden, true)) {
            $this->hidden[] = 'presettable';
        }
    }

    /**
     * Convenience relation to avoid multiple column foreign key references.
     *
     * @return BelongsTo<Presettable,TModel>
     */
    public function presettable(): BelongsTo
    {
        $relation = $this->belongsTo(Presettable::class);
        $relation->withTrashed();

        return $relation;
    }

    #[Override]
    public function setRelation($relation, $value)
    {
        $result = parent::setRelation($relation, $value);

        if ($relation === 'presettable' && $value instanceof Presettable) {
            $this->presettable_id = $value->id;
            $this->entity_id = $value->entity_id;
        }

        return $result;
    }

    public function toArray(?array $parsed = null): array
    {
        // $content = $parsed ?? (method_exists(parent::class, 'toArray') ? parent::toArray() : $this->attributesToArray());
        $content = $parsed ?? parent::toArray();

        if (isset($content['components'])) {
            $components = $content['components'];
            unset($content['components']);

            return array_merge($content, $components);
        }

        return array_merge($content, $this->getComponentsAttribute());
    }

    public function getDynamicFields(): array
    {
        return $this->fields()->pluck('name')->toArray();
    }

    public function isDynamicField(string $field): bool
    {
        // Can't determine dynamic fields without a preset assignment
        // Access attributes directly to avoid recursion through getAttribute
        if (empty($this->attributes['presettable_id'])) {
            return false;
        }

        return in_array($field, $this->getDynamicFields(), true);
    }

    public function getRules(): array
    {
        $fields = [];

        foreach ($this->fields() as $field) {
            $rule = $field->type->getRule();

            if ($field->pivot->is_required) {
                $rule .= '|required';

                if ($field->type === FieldType::ARRAY) {
                    $rule .= '|filled';
                }
            } else {
                $rule .= '|nullable';
            }

            if (isset($field->options->min)) {
                $rule .= '|min:' . $field->options->min;
            }

            if (isset($field->options->max)) {
                $rule .= '|max:' . $field->options->max;
            }

            $fields[$field->name] = mb_trim($rule, '|');

            if ($field->type === FieldType::EDITOR) {
                $fields[$field->name . '.blocks'] = 'array';
                $fields[$field->name . '.blocks.*.type'] = 'string';
                $fields[$field->name . '.blocks.*.data'] = 'array';
            }
        }

        return $fields;
    }

    public function setDefaultPresettable(): void
    {
        $entity_type = static::getEntityType();
        $first_available = static::fetchAvailablePresettables($entity_type::tryFrom($this->getTable()))->firstOrFail();
        $this->setRelation('presettable', $first_available);
    }

    protected static function bootHasDynamicContents(): void
    {
        static::saving(function (Model $model): void {
            $presettable = $model->getRelationValue('presettable');

            if (! in_array(HasDynamicContents::class, class_uses_recursive($model), true)) {
                return;
            }

            if ($presettable) {
                $model->setRelation('presettable', $presettable);
            } elseif (method_exists($model, 'setDefaultPresettable')) {
                $model->setDefaultPresettable();
            }
        });
    }

    /**
     * The entity that belongs to the content.
     */
    protected function entity(): Attribute
    {
        return new Attribute(
            get: function () {
                $presettable = $this->getRelationValue('presettable');

                if (! $presettable) {
                    $this->setDefaultPresettable();
                    $presettable = $this->getRelationValue('presettable');
                }

                return $presettable?->getRelationValue('entity');
            },
        );
    }

    /**
     * The preset that belongs to the content.
     */
    protected function preset(): Attribute
    {
        return new Attribute(
            get: fn () => $this->getRelationValue('presettable')?->getRelationValue('preset'),
        );
    }

    protected function getTextualOnlyAttribute(): string
    {
        $accumulator = '';

        foreach ($this->fields() as $field) {
            if (! $field->type->isTextual()) {
                continue;
            }

            if ($field->type === FieldType::EDITOR) {
                $accumulator .= ' ' . implode(' ', Arr::pluck(((object) $this->{$field->name})->blocks, 'data.text'));
            } else {
                $accumulator .= ' ' . $this->{$field->name};
            }
        }

        return strip_tags($accumulator);
    }

    protected function type(): Attribute
    {
        return new Attribute(
            get: function () {
                if (! $this->relationLoaded('presettable') && $this->presettable_id) {
                    $this->load('presettable');

                    return $this->getRelationValue('presettable')?->getRelationValue('entity')?->name;
                }

                if ($this->presettable_id) {
                    $entity_type = static::getEntityType();

                    return static::fetchAvailablePresets($entity_type::tryFrom($this->getTable()))->firstWhere('id', $this->presettable_id)?->entity?->name;
                }

                $this->setDefaultPresettable();

                return $this->getRelationValue('presettable')?->getRelationValue('entity')?->name;
            },
        );
    }

    protected function getComponentsAttribute(): array
    {
        // Get components from model attributes (for models without translations)
        $components = isset($this->attributes['components'])
            ? json_decode((string) $this->attributes['components'], true)
            : [];

        // Merge with shared_components if available (for models with translations)
        $shared_components = isset($this->attributes['shared_components'])
            ? json_decode((string) $this->attributes['shared_components'], true)
            : [];

        return $this->mergeComponentsValues(array_merge($components, $shared_components));
    }

    protected function setComponentsAttribute(array $components): void
    {
        // Store components in model attributes (for models without translations)
        $this->attributes['components'] = json_encode($this->mergeComponentsValues($components));
    }

    protected function dynamicSlugFields(): array
    {
        return $this->fields()
            ->filter(fn (Field $field): bool => (bool) $field->is_slug)
            ->pluck('name')
            ->toArray();
    }

    protected function casts(): array
    {
        return [
            'components' => 'json',
            'shared_components' => 'json',
            'entity_id' => 'integer',
            'presettable_id' => 'integer',
        ];
    }

    /**
     * Merge components with default values from fields.
     *
     * @param  array<string, mixed>  $components
     * @param  bool|null  $only_translatable  If true, only include translatable fields. If null, include all fields.
     * @return array<string, mixed>
     */
    protected function mergeComponentsValues(array $components, ?bool $only_translatable = null): array
    {
        return $this->fields()
            ->filter(function (Field $field) use ($only_translatable): bool {
                if ($only_translatable === null) {
                    return true;
                }

                // Check if field is translatable
                $is_translatable = $this->isFieldTranslatable($field->name);

                return $only_translatable ? ($is_translatable === true) : ($is_translatable !== true);
            })
            ->mapWithKeys(function (Field $field) use ($components): array {
                $value = data_get($components, $field->name, $field->pivot->default);

                // Ensure ARRAY fields always have an array value (even if empty) to pass validation
                if ($field->type === FieldType::ARRAY && $value === null) {
                    $value = [];
                }

                // Ensure OBJECT/JSON fields always have a valid JSON value (empty object) to pass validation
                // Laravel's 'json' rule doesn't accept null unless 'nullable' is also present
                if (($field->type === FieldType::OBJECT || $field->type === FieldType::EDITOR) && $value === null) {
                    $value = $field->type === FieldType::EDITOR ? ['blocks' => []] : new stdClass();
                }

                return [$field->name => $value];
            })
            ->toArray();
    }

    protected function setComponentAttribute(string $key, $value): void
    {
        // Check if field is translatable (only works if HasTranslatedDynamicContents is used)
        $is_translatable = $this->isFieldTranslatable($key);

        if ($is_translatable === null) {
            // No translation support, use standard components
            $this->setComponentsAttribute([$key => $value]);

            return;
        }

        if ($is_translatable) {
            // Translatable field: will be handled by HasTranslatedDynamicContents
            $this->setComponentsAttribute([$key => $value]);

            return;
        }

        // Non-translatable field: store in shared_components
        $shared_components = isset($this->attributes['shared_components'])
            ? json_decode((string) $this->attributes['shared_components'], true)
            : [];
        $shared_components[$key] = $value;
        $this->attributes['shared_components'] = json_encode($shared_components);
    }

    /**
     * Check if a field is translatable.
     * Returns null if translation support is not available.
     */
    protected function isFieldTranslatable(string $field): ?bool
    {
        // This method will be overridden by HasTranslatedDynamicContents
        return null;
    }

    /**
     * The fields that belong to the content, read from the presettable's
     * frozen snapshot so that content structure is preserved over time.
     *
     * @return Collection<int, Field>
     */
    private function fields(): Collection
    {
        return $this->getRelationValue('presettable')?->getFieldsFromSnapshot() ?? new Collection();
    }
}
