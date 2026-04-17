<?php

declare(strict_types=1);

namespace Modules\Core\Contracts\Geocoding;

interface IGeocodingService
{
    public static function getInstance(): self;

    /**
     * @return array<int, GeocodingResult>|GeocodingResult|null
     */
    public function search(
        string $query,
        ?string $city = null,
        ?string $province = null,
        ?string $country = null,
        int $limit = 1,
    ): array|GeocodingResult|null;

    public function url(GeocodingResult $result): string;
}
