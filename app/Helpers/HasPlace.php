<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\Core\Models\Place;

/**
 * Transparent read/write of geographic fields through {@see Place}, same pattern as {@see HasTranslations}
 * ({@see getAttribute}, {@see setAttribute}, {@see toArray}): bridged keys resolve on the relation; host columns
 * stay in sync where the model still persists them (e.g. {@see Location} + {@see geolocation} Point).
 *
 * @phpstan-require-extends Model
 */
trait HasPlace
{
    public static function bootHasPlace(): void
    {
        static::creating(static function (Model $model): void {
            if (! $model instanceof self) {
                return;
            }

            if (! empty($model->attributes['place_id'] ?? null)) {
                return;
            }

            $place = Place::query()->create($model->buildPlaceRowFromHostAttributes());
            $model->setAttribute('place_id', $place->getKey());
        });
    }

    /**
     * @return BelongsTo<Place, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function getAttribute($key): mixed
    {
        if ($this->shouldReadGeographyFromPlace($key)) {
            $place = $this->resolvePlace();

            if ($place !== null) {
                return match ($key) {
                    'latitude' => $place->latitude,
                    'longitude' => $place->longitude,
                    default => $place->getAttribute($key),
                };
            }
        }

        return parent::getAttribute($key);
    }

    /**
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if (! in_array($key, $this->placeBridgedAttributeKeys(), true)) {
            return parent::setAttribute($key, $value);
        }

        $mutator = 'set' . Str::studly($key) . 'Attribute';

        if (method_exists($this, $mutator)) {
            $this->{$mutator}($value);
            $this->syncGeographyToRelatedPlace();

            return $this;
        }

        parent::setAttribute($key, $value);

        if (! empty($this->attributes['place_id'] ?? null)) {
            $this->syncGeographyToRelatedPlace();
        }

        return $this;
    }

    /**
     * Overlay bridged geography from {@see Place} so JSON / API output matches {@see getAttribute} behaviour
     * (mirrors {@see HasTranslations::toArray} merging related row fields into the serialized array).
     *
     * @param  array<string, mixed>|null  $parsed
     * @return array<string, mixed>
     */
    public function toArray(?array $parsed = null): array
    {
        $content = $parsed ?? (method_exists(parent::class, 'toArray') ? parent::toArray() : $this->attributesToArray());

        if (empty($this->attributes['place_id'] ?? null)) {
            return $content;
        }

        $place = $this->resolvePlace();

        if (! $place instanceof Place) {
            return $content;
        }

        foreach ($this->placeBridgedAttributeKeys() as $field) {
            if (in_array($field, $this->hidden, true)) {
                continue;
            }

            $content[$field] = match ($field) {
                'latitude' => $place->latitude,
                'longitude' => $place->longitude,
                default => $place->getAttribute($field),
            };
        }

        return $content;
    }

    protected function shouldReadGeographyFromPlace(string $key): bool
    {
        if (! in_array($key, $this->placeBridgedAttributeKeys(), true)) {
            return false;
        }

        return ! empty($this->attributes['place_id'] ?? null);
    }

    /**
     * @return array<int, string>
     */
    protected function placeBridgedAttributeKeys(): array
    {
        return ['address', 'city', 'province', 'country', 'postcode', 'zone', 'latitude', 'longitude'];
    }

    protected function resolvePlace(): ?Place
    {
        if (empty($this->attributes['place_id'] ?? null)) {
            return null;
        }

        if ($this->relationLoaded('place')) {
            return $this->getRelation('place');
        }

        return $this->place()->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPlaceRowFromHostAttributes(): array
    {
        return [
            'address' => $this->getAttributeFromColumn('address'),
            'city' => $this->getAttributeFromColumn('city'),
            'province' => $this->getAttributeFromColumn('province'),
            'country' => $this->getAttributeFromColumn('country'),
            'postcode' => $this->getAttributeFromColumn('postcode'),
            'zone' => $this->getAttributeFromColumn('zone'),
            'latitude' => $this->decimalLatitudeFromHost(),
            'longitude' => $this->decimalLongitudeFromHost(),
        ];
    }

    /**
     * Persist canonical geography on {@see Place} from the host row (after mutators / column writes on the host).
     */
    protected function syncGeographyToRelatedPlace(): void
    {
        $place_id = $this->attributes['place_id'] ?? null;

        if ($place_id === null) {
            return;
        }

        $place = Place::query()->find($place_id);

        if (! $place instanceof Place) {
            return;
        }

        $payload = $this->buildPlaceRowFromHostAttributes();

        foreach (['latitude', 'longitude'] as $coord) {
            if (($payload[$coord] ?? null) === null) {
                unset($payload[$coord]);
            }
        }

        $place->fill($payload);
        $place->save();

        if ($this->relationLoaded('place')) {
            $this->setRelation('place', $place->fresh());
        }
    }

    protected function getAttributeFromColumn(string $key): ?string
    {
        $value = $this->getRawOriginal($key);

        return $value !== null ? (string) $value : null;
    }

    protected function decimalLatitudeFromHost(): ?float
    {
        $geo = $this->geolocation ?? null;

        return $geo instanceof Point ? (float) $geo->latitude : null;
    }

    protected function decimalLongitudeFromHost(): ?float
    {
        $geo = $this->geolocation ?? null;

        return $geo instanceof Point ? (float) $geo->longitude : null;
    }
}
