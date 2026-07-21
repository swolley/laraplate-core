<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\OutboxPublisher;
use Modules\Core\Jobs\PublishOutboxEventJob;
use Modules\Core\Models\OutboxEvent;
use Modules\Core\Models\Setting;
use Modules\Core\Services\OutboxRecorder;

it('records an integration event and queues publication after commit', function (): void {
    Queue::fake();
    $aggregate = Setting::query()->forceCreate([
        'key' => 'outbox.test',
        'value' => 'enabled',
    ]);

    $event = app(OutboxRecorder::class)->record('core.test.recorded', $aggregate, [
        'enabled' => true,
    ]);

    expect($event->event_id)->not->toBeEmpty()
        ->and($event->event_type)->toBe('core.test.recorded')
        ->and($event->aggregate_type)->toBe($aggregate->getMorphClass())
        ->and($event->aggregate_id)->toBe((string) $aggregate->getKey())
        ->and($event->payload)->toBe(['enabled' => true])
        ->and($event->published_at)->toBeNull();

    Queue::assertPushed(
        PublishOutboxEventJob::class,
        fn (PublishOutboxEventJob $job): bool => $job->outboxEventId === (int) $event->id,
    );
});

it('publishes an outbox event once and records the attempt', function (): void {
    Queue::fake();
    $aggregate = Setting::query()->forceCreate([
        'key' => 'outbox.publish',
        'value' => 'enabled',
    ]);
    $event = app(OutboxRecorder::class)->record('core.test.published', $aggregate);
    $publisher = new class implements OutboxPublisher
    {
        public int $calls = 0;

        public function publish(OutboxEvent $event): void
        {
            $this->calls++;
        }
    };
    $job = new PublishOutboxEventJob((int) $event->id);

    $job->handle($publisher);
    $job->handle($publisher);

    expect($publisher->calls)->toBe(1)
        ->and($event->fresh()->published_at)->not->toBeNull()
        ->and($event->fresh()->publish_attempts)->toBe(1);
});

it('rolls back the event and makes a queued publication harmless', function (): void {
    Queue::fake();
    $aggregate = Setting::query()->forceCreate([
        'key' => 'outbox.rollback',
        'value' => 'enabled',
    ]);

    expect(fn () => DB::transaction(function () use ($aggregate): void {
        app(OutboxRecorder::class)->record('core.test.rolled-back', $aggregate);

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback');

    expect(OutboxEvent::query()->where('event_type', 'core.test.rolled-back')->exists())->toBeFalse();

    $publisher = new class implements OutboxPublisher
    {
        public int $calls = 0;

        public function publish(OutboxEvent $event): void
        {
            $this->calls++;
        }
    };
    $queued_job = Queue::pushed(PublishOutboxEventJob::class)->first();
    $queued_job->handle($publisher);

    expect($publisher->calls)->toBe(0);
});
