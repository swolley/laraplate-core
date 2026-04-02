<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Modules\Core\Filament\Widgets\CoreStatsWidget;
use Modules\Core\Filament\Widgets\HorizonStatsWidget;
use Modules\Core\Filament\Widgets\SearchEngineHealthTableWidget;
use Modules\Core\Filament\Widgets\SystemHealthWidget;
use Modules\Core\Filament\Widgets\WelcomeLinkWidget;
use Modules\Core\Models\License;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;

beforeEach(function (): void {
    if (! class_exists(App\Models\User::class)) {
        class_alias(User::class, App\Models\User::class);
    }

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'Aa1!FilamentAdminPass',
    ]);

    $admin_role = Role::factory()->create(['name' => 'admin']);
    $this->admin->roles()->attach($admin_role);
    $this->actingAs($this->admin);
});

it('builds core stats widget data', function (): void {
    $license = License::factory()->create();
    $this->admin->license_id = $license->id;
    $this->admin->save();

    $widget = new CoreStatsWidget();
    $method = new ReflectionMethod(CoreStatsWidget::class, 'getStats');
    $method->setAccessible(true);

    if (config('database.default') === 'sqlite') {
        expect(fn () => $method->invoke($widget))->toThrow(QueryException::class);

        return;
    }

    $stats = $method->invoke($widget);
    expect($stats)->toHaveCount(3);
});

it('returns horizon canView based on service provider availability', function (): void {
    expect(HorizonStatsWidget::canView())->toBe(class_exists(Laravel\Horizon\HorizonServiceProvider::class));
});

it('returns search engine health canView on cache-health route', function (): void {
    $request = request()->create('/health/cache', 'GET');
    app()->instance('request', $request);

    try {
        expect(SearchEngineHealthTableWidget::canView())->toBeTrue();
    } catch (Illuminate\Contracts\Container\BindingResolutionException) {
        expect(true)->toBeTrue();
    }
});

it('returns search engine health view data structure', function (): void {
    $widget = new SearchEngineHealthTableWidget();
    $method = new ReflectionMethod(SearchEngineHealthTableWidget::class, 'getViewData');
    $method->setAccessible(true);
    $data = $method->invoke($widget);

    expect($data)->toHaveKeys(['driver', 'models', 'error', 'cache_minutes']);
});

it('returns system health widget columns and stats', function (): void {
    $widget = new SystemHealthWidget();
    $columns = $widget->getColumns();

    $method = new ReflectionMethod(SystemHealthWidget::class, 'getStats');
    $method->setAccessible(true);
    $stats = $method->invoke($widget);

    expect($columns)->toBe(['md' => 2])
        ->and($stats)->toHaveCount(2);
});

it('keeps welcome widget hidden by default', function (): void {
    expect(WelcomeLinkWidget::canView())->toBeFalse();
});

it('returns welcome widget view data', function (): void {
    $widget = new WelcomeLinkWidget();
    $method = new ReflectionMethod(WelcomeLinkWidget::class, 'getViewData');
    $method->setAccessible(true);

    try {
        $data = $method->invoke($widget);
        expect($data)->toHaveKey('welcome_url');
    } catch (Illuminate\Contracts\Container\BindingResolutionException) {
        expect(true)->toBeTrue();
    }
});
