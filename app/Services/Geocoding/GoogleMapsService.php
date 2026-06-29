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
        $api_key = config('services.geocoding.api_key', '');
        $this->api_key = is_string($api_key) ? $api_key : '';
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

        $payload = $response->json();

        if (! is_array($payload)) {
            return $limit > 1 ? [] : null;
        }

        $status = $payload['status'] ?? null;

        if (! $response->successful() || $status !== 'OK') {
            return $limit > 1 ? [] : null;
        }

        $results = $payload['results'] ?? null;

        if (! is_array($results) || $results === []) {
            return $limit > 1 ? [] : null;
        }

        if ($limit > 1) {
            return array_values(array_map(
                fn (mixed $result): GeocodingResult => $this->getAddressDetails($this->normalizeResult($result)),
                $results,
            ));
        }

        return $this->getAddressDetails($this->normalizeResult($results[0]));
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
        $components = $this->extractAddressComponents($result);

        $route = $this->componentString($components, 'route');
        $street_number = $this->componentString($components, 'street_number');

        return new GeocodingResult(
            name: $this->componentString($components, 'locality', 'administrative_area_level_3') ?? $route ?? 'location',
            address: $route !== null ? $route . ($street_number !== null ? ' ' . $street_number : '') : null,
            city: $this->componentString($components, 'locality', 'administrative_area_level_3'),
            province: $this->componentString($components, 'administrative_area_level_1'),
            country: $this->componentString($components, 'country'),
            postcode: $this->componentString($components, 'postal_code'),
            latitude: $this->extractCoordinate($result, 'lat'),
            longitude: $this->extractCoordinate($result, 'lng'),
            zone: $this->componentString($components, 'sublocality'),
        );
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, string>
     */
    private function extractAddressComponents(array $result): array
    {
        $components = [];
        $address_components = $result['address_components'] ?? null;

        if (! is_array($address_components)) {
            return $components;
        }

        foreach ($address_components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $types = $component['types'] ?? null;

            if (! is_array($types) || ! isset($types[0]) || ! is_string($types[0])) {
                continue;
            }

            $long_name = $component['long_name'] ?? null;

            if (! is_string($long_name)) {
                continue;
            }

            $components[$types[0]] = $long_name;
        }

        return $components;
    }

    /**
     * @param  array<string, string>  $components
     */
    private function componentString(array $components, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($components[$key])) {
                return $components[$key];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeResult(mixed $result): array
    {
        return is_array($result) ? $result : [];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function extractCoordinate(array $result, string $axis): ?float
    {
        $geometry = $result['geometry'] ?? null;

        if (! is_array($geometry)) {
            return null;
        }

        $location = $geometry['location'] ?? null;

        if (! is_array($location) || ! isset($location[$axis]) || ! is_numeric($location[$axis])) {
            return null;
        }

        return (float) $location[$axis];
    }
}
