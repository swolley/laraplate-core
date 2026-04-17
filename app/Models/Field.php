<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Validation\Rule;
use Modules\Core\Casts\FieldType;
use Modules\Core\Casts\ObjectCast;
use Modules\Core\Helpers\HasActivation;
use Modules\Core\Models\Pivot\Fieldable;
use Modules\Core\Observers\FieldObserver;
use Modules\Core\Overrides\Model;
use Override;

/**
 * @property-read object $options
 * @mixin IdeHelperField
 */
#[ObservedBy(FieldObserver::class)]
final class Field extends Model
{
    // region Traits
    use HasActivation {
        HasActivation::casts as private activationCasts;
    }
    // endregion

    /**
     * The attributes that are mass assignable.
     */
    #[Override]
    protected $fillable = [
        'name',
        'type',
        'options',
    ];

    #[Override]
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function getAttribute(mixed $key): mixed
    {
        if (property_exists($this, 'pivot') && $this->pivot !== null && isset($this->pivot->{$key})) {
            return $this->pivot->{$key};
        }

        return parent::getAttribute($key);
    }

    public function setAttribute(mixed $key, mixed $value): mixed
    {
        if (property_exists($this, 'pivot') && $this->pivot !== null && array_key_exists($key, $this->pivot->getAttributes())) {
            // @phpstan-ignore assign.propertyReadOnly
            data_set($this->pivot, $key, $value);

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * @return BelongsToMany<Preset,Field,Fieldable,'pivot'>
     */
    public function presets(): BelongsToMany
    {
        return $this->belongsToMany(Preset::class, 'fieldables')->using(Fieldable::class)->withTimestamps()->withPivot(['order_column', 'is_required', 'default']);
    }

    #[Override]
    public function toArray(): array
    {
        $field = parent::toArray();

        if (isset($field['pivot'])) {
            $pivot = $field['pivot'];
            unset($field['pivot']);
            $field = array_merge($field, $pivot);
        } elseif (property_exists($this, 'pivot') && $this->pivot !== null) {
            $field = array_merge($field, $this->pivot->toArray());
        }

        return $field;
    }

    public function getRules(): array
    {
        $rules = parent::getRules();
        $rules[Model::DEFAULT_RULE] = array_merge($rules[Model::DEFAULT_RULE], [
            'is_active' => 'boolean',
            'type' => ['required', 'string', Rule::enum(FieldType::class)],
        ]);
        $rules['create'] = array_merge($rules['create'], [
            'name' => ['required', 'string', 'max:255', 'unique:fields,name'],
        ]);
        $rules['update'] = array_merge($rules['update'], [
            'name' => ['sometimes', 'string', 'max:255', 'unique:fields,name,' . $this->id],
        ]);

        return $rules;
    }

    protected function casts(): array
    {
        return array_merge($this->activationCasts(), [
            'options' => ObjectCast::class,
            'type' => FieldType::class,
            'is_translatable' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'datetime',
        ]);
    }
}
