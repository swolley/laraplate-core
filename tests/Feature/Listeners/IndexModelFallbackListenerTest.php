<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Listeners\IndexModelFallbackListener;
use Modules\Core\Search\Jobs\IndexInSearchJob;

beforeEach(function (): void {
    config(['scout.driver' => 'null']);
    $this->content = createMinimalTestContentForComments();
});

it('skips fallback indexing when another listener already handled the event', function (): void {
    Queue::fake();

    $event = new ModelRequiresIndexing($this->content);
    $event->markAsHandled();

    app(IndexModelFallbackListener::class)->handle($event);

    Queue::assertNothingPushed();
});

it('dispatches indexing job when event is not handled', function (): void {
    Queue::fake();

    app(IndexModelFallbackListener::class)->handle(new ModelRequiresIndexing($this->content));

    Queue::assertPushed(IndexInSearchJob::class);
});

it('indexes synchronously when sync flag is set', function (): void {
    app(IndexModelFallbackListener::class)->handle(new ModelRequiresIndexing($this->content, sync: true));

    expect($this->content->exists)->toBeTrue();
});
