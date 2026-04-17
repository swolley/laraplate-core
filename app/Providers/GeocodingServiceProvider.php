<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\Geocoding\IGeocodingService;
use Modules\Core\Services\Geocoding\GoogleMapsService;
use Modules\Core\Services\Geocoding\NominatimService;
use Override;

final class GeocodingServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton(IGeocodingService::class, static fn (Application $app): GoogleMapsService|NominatimService => match (config('services.geocoding.provider', 'nominatim')) {
            'google' => GoogleMapsService::getInstance(),
            default => NominatimService::getInstance(),
        });
    }
}
