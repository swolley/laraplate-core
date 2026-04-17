<?php

declare(strict_types=1);

namespace Modules\Core\Services\Geocoding;

use Illuminate\Support\Facades\Http;
use Modules\Core\Contracts\Geocoding\GeocodingResult;
use Override;

final class GoogleMapsService extends AbstractGeocodingService
{
    public const string BASE_URL = 'https://maps.googleapis.com/maps/api/geocode';

    private readonly string $api_key;

    /**
     * Protected constructor to enforce singleton pattern.
     */
    private function __construct()
    {
        $this->api_key = (string) config('services.geocoding.api_key', '');
    }

    #[Override]
    protected function performSearch(
        string $query,
        ?string $city,
        ?string $province,
        ?string $country,
        int $limit,
    ): array|GeocodingResult|null {
        $address_components = array_filter([$query, $city, $province, $country]);
        $full_address = implode(', ', $address_components);

        $response = Http::get(self::BASE_URL . '/json', [
            'address' => $full_address,
            'key' => $this->api_key,
            'limit' => $limit,
        ]);

        if (! $response->successful() || $response->json()['status'] !== 'OK') {
            return $limit > 1 ? [] : null;
        }

        $results = $response->json()['results'];

        if ($limit > 1) {
            return array_map($this->getAddressDetails(...), $results);
        }

        return $this->getAddressDetails($results[0]);
    }

    #[Override]
    protected function getSearchUrl(string $search_string): string
    {
        return self::BASE_URL . '/maps?q=' . $search_string . '&key=' . $this->api_key;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    #[Override]
    protected function getAddressDetails(array $result): GeocodingResult
    {
        $components = [];

        foreach ($result['address_components'] as $component) {
            $type = $component['types'][0];
            $components[$type] = $component['long_name'];
        }

        $route = $components['route'] ?? null;
        $street_number = $components['street_number'] ?? null;

        return new GeocodingResult(
            name: $components['locality'] ?? $components['administrative_area_level_3'] ?? $route ?? 'location',
            address: $route ? $route . ($street_number ? ' ' . $street_number : '') : null,
            city: $components['locality'] ?? $components['administrative_area_level_3'] ?? null,
            province: $components['administrative_area_level_1'] ?? null,
            country: $components['country'] ?? null,
            postcode: $components['postal_code'] ?? null,
            latitude: isset($result['geometry']['location']['lat']) ? (float) $result['geometry']['location']['lat'] : null,
            longitude: isset($result['geometry']['location']['lng']) ? (float) $result['geometry']['location']['lng'] : null,
            zone: $components['sublocality'] ?? null,
        );
    }
}
