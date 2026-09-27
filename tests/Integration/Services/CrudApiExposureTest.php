<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Http\Middleware\ApplyDatabaseSettingsOverlay;
use Modules\Core\Models\Setting;
use Modules\Core\Services\PerModelSettingResolver;
use Modules\Core\Support\CrudApiExposure;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    app(PerModelSettingResolver::class)->flush();
});

it('lets the database setting win over a process-level config set', function (): void {
    // Artisan tools and tests used to flip core.crud.expose_api with config()->set().
    // The per-request overlay copies every dotted setting from the DB, so that flip
    // is discarded on the next HTTP request — DB wins by design.
    config()->set('core.crud.expose_api', true);

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'crud.expose_api',
        'module' => 'Core',
        'value' => false,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'core',
        'description' => 'test',
    ]);

    app(PerModelSettingResolver::class)->flush();

    app(ApplyDatabaseSettingsOverlay::class)->handle(
        Request::create('/api/v1/select/core/users', 'GET'),
        static fn (): Response => new Response,
    );

    expect(config('core.crud.expose_api'))->toBeFalse();
});

it('enables the crud api for a process by writing the database setting', function (): void {
    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'crud.expose_api',
        'module' => 'Core',
        'value' => false,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'core',
        'description' => 'test',
    ]);

    app(PerModelSettingResolver::class)->flush();

    CrudApiExposure::runEnabled(function (): void {
        app(ApplyDatabaseSettingsOverlay::class)->handle(
            Request::create('/api/v1/select/core/users', 'GET'),
            static fn (): Response => new Response,
        );

        expect(config('core.crud.expose_api'))->toBeTrue();
    });

    // Restored after the block so a long-lived worker cannot leave the API open.
    app(PerModelSettingResolver::class)->flush();
    app(ApplyDatabaseSettingsOverlay::class)->handle(
        Request::create('/api/v1/select/core/users', 'GET'),
        static fn (): Response => new Response,
    );

    expect(config('core.crud.expose_api'))->toBeFalse();
});
