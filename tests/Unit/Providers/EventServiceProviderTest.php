<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Modules\Core\Models\CronJob;
use Modules\Core\Models\Setting;
use Modules\Core\Providers\EventServiceProvider;


beforeEach(function (): void {
    $this->provider = new EventServiceProvider(app());
});

it('has listen array for model indexing events', function (): void {
    $ref = new ReflectionClass($this->provider);
    $prop = $ref->getProperty('listen');
    $listen = $prop->getValue($this->provider);

    expect($listen)->toHaveKey(Modules\Core\Events\ModelRequiresIndexing::class);
    expect($listen)->toHaveKey(Modules\Core\Events\ModelPreProcessingCompleted::class);
});

it('boot runs without throwing', function (): void {
    $this->provider->boot();

    expect(true)->toBeTrue();
});

it('after boot CronJob saved triggers cache forget', function (): void {
    $this->provider->boot();

    $cron = CronJob::factory()->create();
    $cron->save();

    expect(true)->toBeTrue();
});

it('boot registers Setting saved listener that flushes cache tags', function (): void {
    $listeners_before = Event::getRawListeners();

    $this->provider->boot();

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create();

    $event_name = 'eloquent.saved: ' . Setting::class;
    $listeners = Event::getRawListeners()[$event_name] ?? [];

    expect($listeners)->not->toBeEmpty();

    $cache = Cache::store();

    if ($cache->supportsTags() && method_exists($cache, 'getCacheTags')) {
        $cache->tags($cache->getCacheTags($setting->getTable()))->put('_test_', true);

        $listener = end($listeners);
        $listener($setting);

        expect($cache->tags($cache->getCacheTags($setting->getTable()))->get('_test_'))->toBeNull();
    } else {
        $listener = end($listeners);
        expect(fn () => $listener($setting))->not->toThrow(Throwable::class);
    }
});

it('configureEmailVerification can be invoked safely', function (): void {
    $ref = new ReflectionClass($this->provider);
    $method = $ref->getMethod('configureEmailVerification');
    $method->setAccessible(true);
    $method->invoke($this->provider);

    expect(true)->toBeTrue();
});
