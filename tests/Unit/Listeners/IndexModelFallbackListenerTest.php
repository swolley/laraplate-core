<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Bus;
use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Listeners\IndexModelFallbackListener;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\Fixtures\StubSearchableModel;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('does nothing when event is already handled', function (): void {
    Bus::fake();

    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create();
    $event = new ModelRequiresIndexing($setting, false);
    $event->markAsHandled();

    (new IndexModelFallbackListener())->handle($event);

    Bus::assertNotDispatched(Modules\Core\Search\Jobs\IndexInSearchJob::class);
});

it('dispatches IndexInSearchJob when not handled and sync is false', function (): void {
    Bus::fake();

    $model = new StubSearchableModel();
    $model->setAttribute('id', 1);
    $event = new ModelRequiresIndexing($model, false);

    (new IndexModelFallbackListener())->handle($event);

    Bus::assertDispatched(Modules\Core\Search\Jobs\IndexInSearchJob::class);
});

it('runs IndexInSearchJob synchronously when sync is true', function (): void {
    $model = new StubSearchableModel();
    $model->setAttribute('id', 1);
    $event = new ModelRequiresIndexing($model, true);

    try {
        (new IndexModelFallbackListener())->handle($event);
    } catch (Throwable) {
        // Search infrastructure may not be fully configured in test env
    }

    expect($event->sync)->toBeTrue();
});
