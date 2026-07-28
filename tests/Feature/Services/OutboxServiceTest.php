<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Contracts\OutboxPublisher;
use Modules\Core\Jobs\PublishOutboxEventJob;
use Modules\Core\Models\OutboxEvent;
use Modules\Core\Models\Setting;
use Modules\Core\Services\OutboxRecorder;

it('records an integration event and queues publication after commit', function (): void {
    Queue::fake();
    $aggregate = Setting::query()->forceCreate([
        'name' => 'outbox.test',
        'value' => 'enabled',
        'description' => '',
    ]);

    $event = app(OutboxRecorder::class)->record($aggregate, 'core.test.recorded', [
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
        'name' => 'outbox.publish',
        'value' => 'enabled',
        'description' => '',
    ]);
    $event = app(OutboxRecorder::class)->record($aggregate, 'core.test.published');
    $publisher = new class implements OutboxPublisher
    {
        public int $calls = 0;

        public function publish(OutboxEvent $event): void
        {
            $this->calls++;
        }
    };
    $job = new PublishOutboxEventJob((int) $event->id, (string) $event->getConnection()->getName());

    $job->handle($publisher);
    $job->handle($publisher);

    expect($publisher->calls)->toBe(1)
        ->and($event->fresh()->published_at)->not->toBeNull()
        ->and($event->fresh()->publish_attempts)->toBe(1);
});

it('rolls back the event and does not queue publication', function (): void {
    Queue::fake();
    $aggregate = Setting::query()->forceCreate([
        'name' => 'outbox.rollback',
        'value' => 'enabled',
        'description' => '',
    ]);

    expect(fn () => DB::transaction(function () use ($aggregate): void {
        app(OutboxRecorder::class)->record($aggregate, 'core.test.rolled-back');

        throw new RuntimeException('rollback');
    }))->toThrow(RuntimeException::class, 'rollback');

    expect(OutboxEvent::query()->where('event_type', 'core.test.rolled-back')->exists())->toBeFalse();

    Queue::assertNothingPushed();
});

it('records and publishes on the owning model connection after that connection commits', function (): void {
    Queue::fake();
    config()->set('database.connections.core-secondary', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    Schema::connection('core-secondary')->create((new OutboxEvent)->getTable(), function (Blueprint $table): void {
        $table->id();
        $table->uuid('event_id')->unique();
        $table->string('event_type');
        $table->string('aggregate_type');
        $table->string('aggregate_id');
        $table->json('payload');
        $table->timestamp('occurred_at');
        $table->timestamp('published_at')->nullable();
        $table->unsignedInteger('publish_attempts')->default(0);
        $table->text('last_error')->nullable();
        $table->timestamps();
    });

    $aggregate = (new Setting)->setConnection('core-secondary');
    $aggregate->id = 781;
    $aggregate->exists = true;
    $connection = $aggregate->getConnection();
    $connection->beginTransaction();

    $event = app(OutboxRecorder::class)->record($aggregate, 'core.test.secondary', [
        'connection' => 'secondary',
    ]);

    expect($event->getConnectionName())->toBe('core-secondary')
        ->and($connection->table((new OutboxEvent)->getTable())->where('id', $event->id)->exists())->toBeTrue()
        ->and(OutboxEvent::query()->where('event_type', 'core.test.secondary')->exists())->toBeFalse();
    Queue::assertNothingPushed();

    $connection->commit();

    Queue::assertPushed(
        PublishOutboxEventJob::class,
        fn (PublishOutboxEventJob $job): bool => $job->outboxEventId === (int) $event->id
            && $job->connectionName === 'core-secondary',
    );

    $publisher = new class implements OutboxPublisher
    {
        public int $calls = 0;

        public function publish(OutboxEvent $event): void
        {
            expect($event->getConnectionName())->toBe('core-secondary');
            $this->calls++;
        }
    };
    $job = new PublishOutboxEventJob((int) $event->id, 'core-secondary');
    $job->handle($publisher);

    expect($publisher->calls)->toBe(1)
        ->and($connection->table((new OutboxEvent)->getTable())->where('id', $event->id)->value('publish_attempts'))->toBe(1)
        ->and($connection->transactionLevel())->toBe(0);
});
