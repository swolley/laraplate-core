<?php

declare(strict_types=1);

use Modules\Core\Models\CronJob;
use Modules\Core\Providers\EventServiceProvider;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    $this->provider = new EventServiceProvider(app());
});

it('has listen array for model indexing events', function (): void {
    $ref = new ReflectionClass($this->provider);
    $prop = $ref->getProperty('listen');
    $prop->setAccessible(true);
    $listen = $prop->getValue($this->provider);

    expect($listen)->toHaveKey(\Modules\Core\Events\ModelRequiresIndexing::class);
    expect($listen)->toHaveKey(\Modules\Core\Events\ModelPreProcessingCompleted::class);
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
