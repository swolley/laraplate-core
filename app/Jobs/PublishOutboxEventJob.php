<?php

declare(strict_types=1);

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Contracts\OutboxPublisher;
use Modules\Core\Models\OutboxEvent;
use Throwable;

final class PublishOutboxEventJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 300];

    public function __construct(public readonly int $outboxEventId)
    {
        $this->onQueue('outbox');
    }

    public function uniqueId(): string
    {
        return (string) $this->outboxEventId;
    }

    public function handle(OutboxPublisher $publisher): void
    {
        $event = OutboxEvent::query()->find($this->outboxEventId);

        if ($event === null || $event->published_at !== null) {
            return;
        }

        try {
            $publisher->publish($event);

            $event->forceFill([
                'published_at' => now(),
                'publish_attempts' => $event->publish_attempts + 1,
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $event->forceFill([
                'publish_attempts' => $event->publish_attempts + 1,
                'last_error' => mb_substr($exception->getMessage(), 0, 65535),
            ])->save();

            throw $exception;
        }
    }
}
