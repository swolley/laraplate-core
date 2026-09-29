<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessing;
use Modules\Core\Models\Setting;
use Modules\Core\Services\SettingsCacheCoordinator;

it('applies a setting changed by another process before the next job runs', function (): void {
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'overlay.probe',
        'module' => 'Core',
        'type' => 'string',
        'value' => 'before',
        'choices' => null,
        'encrypted' => false,
    ]);

    expect(config('core.overlay.probe'))->toBe('before');

    // Another process saves the setting: the row and the persistent cache change, this
    // process's config does not.
    Setting::query()->withoutGlobalScopes()->whereKey($setting->getKey())
        ->update(['value' => json_encode('after')]);
    app(SettingsCacheCoordinator::class)->flushAll();
    app()->forgetScopedInstances();

    expect(config('core.overlay.probe'))->toBe('before');

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn([]);

    event(new JobProcessing('database', $job));

    expect(config('core.overlay.probe'))->toBe('after');
});

it('leaves the request config alone for a job run on the sync queue', function (): void {
    Setting::factory()->persistedWithoutApprovalCapture()->create([
        'name' => 'overlay.sync_probe',
        'module' => 'Core',
        'type' => 'string',
        'value' => 'from database',
        'choices' => null,
        'encrypted' => false,
    ]);
    config()->set('core.overlay.sync_probe', 'set in this request');

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('payload')->andReturn([]);

    event(new JobProcessing('sync', $job));

    expect(config('core.overlay.sync_probe'))->toBe('set in this request');
});
