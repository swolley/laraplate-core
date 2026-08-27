<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Http\Middleware\ApplyDatabaseSettingsOverlay;
use Modules\Core\Models\Setting;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\PerModelSettingResolver;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    app(PerModelSettingResolver::class)->flush();
});

it('overlays dotted settings onto the config repository per request', function (): void {
    config()->set('core.demo_flag', 'boot-value');

    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'core.demo_flag',
        'value' => 'runtime-value',
        'type' => SettingTypeEnum::String,
        'group_name' => 'core_demo',
        'description' => 'test',
    ]);

    app(PerModelSettingResolver::class)->flush();

    $response = app(ApplyDatabaseSettingsOverlay::class)->handle(
        Request::create('/app/test', 'GET'),
        static fn (): Response => new Response,
    );

    expect($response)->toBeInstanceOf(Response::class)
        ->and(config('core.demo_flag'))->toBe('runtime-value');
});

it('gives each request its own settings resolver', function (): void {
    $first = app(PerModelSettingResolver::class);

    expect(app(PerModelSettingResolver::class))->toBe($first);

    // Octane ends a request by forgetting scoped instances; a process-wide resolver
    // would keep serving the settings it loaded for an earlier request.
    app()->forgetScopedInstances();

    expect(app(PerModelSettingResolver::class))->not->toBe($first);
});

it('shares one authorization service inside a request and drops it at the boundary', function (): void {
    $first = app(AuthorizationService::class);

    // once() binds its memo to $this, so a transient service memoizes nothing across
    // the several collaborators that resolve it during a single request.
    expect(app(AuthorizationService::class))->toBe($first);

    app()->forgetScopedInstances();

    expect(app(AuthorizationService::class))->not->toBe($first);
});
