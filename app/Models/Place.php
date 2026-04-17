<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Support\Str;
use Modules\Core\Overrides\Model;
use Override;

/**
 * Canonical postal / geographic payload shared across modules (e.g. Cms Location, Business Site).
 *
 * @mixin IdeHelperPlace
 */
class Place extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'address',
        'city',
        'province',
        'country',
        'postcode',
        'zone',
        'latitude',
        'longitude',
    ];

    /**
     * Geography fields for search documents (shape aligned with former Location-only payloads).
     *
     * @return array{
     *     address: string|null,
     *     city: string|null,
     *     province: string|null,
     *     country: string|null,
     *     postcode: string|null,
     *     zone: string|null,
     *     geocode: array{0: float, 1: float}
     * }
     */
    public function searchDocumentGeographyFields(): array
    {
        return [
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'country' => $this->country,
            'postcode' => $this->postcode,
            'zone' => $this->zone,
            'geocode' => [
                (float) ($this->latitude ?? 0.0),
                (float) ($this->longitude ?? 0.0),
            ],
        ];
    }

    /**
     * Slug segment derived from country (e.g. for hierarchical paths in consuming modules).
     */
    public function countryPathSegment(): string
    {
        return Str::slug((string) ($this->country ?? ''));
    }

    #[Override]
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
