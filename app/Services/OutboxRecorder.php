<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Core\Jobs\PublishOutboxEventJob;
use Modules\Core\Models\OutboxEvent;

final readonly class OutboxRecorder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(Model $owner, string $event_type, array $payload = []): OutboxEvent
    {
        $connection = $owner->getConnection();
        $connection_name = (string) $connection->getName();
        $event = (new OutboxEvent)
            ->setConnection($connection_name)
            ->newQuery()
            ->create([
                'event_id' => (string) Str::uuid(),
                'event_type' => $event_type,
                'aggregate_type' => $owner->getMorphClass(),
                'aggregate_id' => (string) $owner->getKey(),
                'payload' => $payload,
                'occurred_at' => now(),
            ]);

        $connection->afterCommit(
            static fn () => PublishOutboxEventJob::dispatch((int) $event->id, $connection_name),
        );

        return $event;
    }
}
