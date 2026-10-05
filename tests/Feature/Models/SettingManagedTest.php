<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\Support\HttpContext;

uses(RefreshDatabase::class);

function managedSetting(bool $managed, mixed $value = 384): Setting
{
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'search.vector.dimensions',
        'module' => 'Core',
        'type' => 'integer',
        'value' => $value,
        'choices' => null,
        'group_name' => 'search',
    ]);

    $setting->forceFill(['managed' => $managed])->saveQuietly();

    return $setting->fresh();
}

it('does not let the managed flag be mass assigned', function (): void {
    expect((new Setting)->isFillable('managed'))->toBeFalse();
});

it('casts the managed flag to a bool', function (): void {
    expect(managedSetting(true)->managed)->toBeTrue();
});

it('writes a managed value and the settings overlay follows', function (): void {
    managedSetting(true);

    Setting::writeManaged('search.vector.dimensions', 1024);

    expect(Setting::query()->where('name', 'search.vector.dimensions')->value('value'))->toBe(1024)
        ->and(config('core.search.vector.dimensions'))->toBe(1024);
});

it('writes a managed value without creating a pending approval', function (): void {
    $setting = managedSetting(true);
    HttpContext::panelActorWithoutApproval(new Setting, ['select', 'update']);

    Setting::writeManaged('search.vector.dimensions', 768);

    expect($setting->modifications()->activeOnly()->count())->toBe(0)
        ->and($setting->fresh()->value)->toBe(768);
});

it('refuses to write a setting that is not managed', function (): void {
    managedSetting(false);

    expect(fn () => Setting::writeManaged('search.vector.dimensions', 1))->toThrow(InvalidArgumentException::class)
        ->and(Setting::query()->where('name', 'search.vector.dimensions')->value('value'))->toBe(384);
});

it('refuses to write a setting that does not exist', function (): void {
    expect(fn () => Setting::writeManaged('search.vector.nope', 1))->toThrow(InvalidArgumentException::class);
});
