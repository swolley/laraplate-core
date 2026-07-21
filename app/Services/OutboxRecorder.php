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
    public function record(string $event_type, Model $aggregate, array $payload = []): OutboxEvent
    {
        $event = OutboxEvent::query()->create([
            'event_id' => (string) Str::uuid(),
            'event_type' => $event_type,
            'aggregate_type' => $aggregate->getMorphClass(),
            'aggregate_id' => (string) $aggregate->getKey(),
            'payload' => $payload,
            'occurred_at' => now(),
        ]);

        PublishOutboxEventJob::dispatch((int) $event->id)->afterCommit();

        return $event;
    }
}
