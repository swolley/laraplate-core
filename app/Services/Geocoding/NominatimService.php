<?php

declare(strict_types=1);

namespace Modules\Core\Services\Geocoding;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Modules\Core\Contracts\Geocoding\GeocodingResult;
use Override;

final class NominatimService extends AbstractGeocodingService
{
    public const string BASE_URL = 'https://nominatim.openstreetmap.org';

    #[Override]
    protected function performSearch(
        string $query,
        ?string $city,
        ?string $province,
        ?string $country,
        int $limit,
    ): array|GeocodingResult|null {
        $params = [
            'q' => $query,
            'format' => 'json',
            'addressdetails' => 1,
            'limit' => $limit,
        ];

        if (! in_array($city, [null, '', '0'], true)) {
            $params['city'] = $city;
        }

        if (! in_array($province, [null, '', '0'], true)) {
            $params['province'] = $province;
        }

        if (! in_array($country, [null, '', '0'], true)) {
            $params['country'] = $country;
        }

        /** @var Response $response */
        $response = Http::withHeaders([
            'User-Agent' => config('app.name') . ' Application',
        ])->get(self::BASE_URL . '/search', $params);

        if (! $response->successful() || $response->json() === []) {
            return $limit > 1 ? [] : null;
        }

        $result = $response->json();

        if ($limit > 1) {
            return array_map($this->getAddressDetails(...), $result);
        }

        return $this->getAddressDetails($result[0]);
    }

    /**
     * @param array{
     *     address: array{
     *         road: string|null,
     *         house_number: string|null,
     *         city: string|null,
     *         town: string|null,
     *         village: string|null,
     *         state: string|null,
     *         country: string|null,
     *         postcode: string|null,
     *         suburb: string|null,
     *     },
     *     lat: float|null,
     *     lon: float|null,
     * } $result
     */
    #[Override]
    protected function getAddressDetails(array $result): GeocodingResult
    {
        $address = $result['address'];
        $road = $address['road'] ?? null;
        $house_number = $address['house_number'] ?? null;

        return new GeocodingResult(
            name: $address['city'] ?? $address['town'] ?? $address['village'] ?? $road ?? 'location',
            address: $road ? $road . ($house_number ? ' ' . $house_number : '') : null,
            city: $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
            province: $address['state'] ?? null,
            country: $address['country'] ?? null,
            postcode: $address['postcode'] ?? null,
            latitude: isset($result['lat']) ? (float) $result['lat'] : null,
            longitude: isset($result['lon']) ? (float) $result['lon'] : null,
            zone: $address['suburb'] ?? null,
        );
    }

    #[Override]
    protected function getSearchUrl(string $search_string): string
    {
        return self::BASE_URL . '/search?q=' . $search_string;
    }
}
