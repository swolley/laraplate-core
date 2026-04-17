<?php

declare(strict_types=1);

namespace Modules\Core\Contracts\Geocoding;

/**
 * Normalized geocoding hit, independent of Cms Location.
 */
final readonly class GeocodingResult
{
    public function __construct(
        public ?string $name = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $province = null,
        public ?string $country = null,
        public ?string $postcode = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $zone = null,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            name: isset($attributes['name']) ? (string) $attributes['name'] : null,
            address: isset($attributes['address']) ? (string) $attributes['address'] : null,
            city: isset($attributes['city']) ? (string) $attributes['city'] : null,
            province: isset($attributes['province']) ? (string) $attributes['province'] : null,
            country: isset($attributes['country']) ? (string) $attributes['country'] : null,
            postcode: isset($attributes['postcode']) ? (string) $attributes['postcode'] : null,
            latitude: isset($attributes['latitude']) ? (float) $attributes['latitude'] : null,
            longitude: isset($attributes['longitude']) ? (float) $attributes['longitude'] : null,
            zone: isset($attributes['zone']) ? (string) $attributes['zone'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'country' => $this->country,
            'postcode' => $this->postcode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'zone' => $this->zone,
        ];
    }
}
