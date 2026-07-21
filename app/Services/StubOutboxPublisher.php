<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Modules\Core\Contracts\OutboxPublisher;
use Modules\Core\Models\OutboxEvent;

final readonly class StubOutboxPublisher implements OutboxPublisher
{
    public function publish(OutboxEvent $event): void
    {
        // Applications bind a transport-specific publisher when external delivery is required.
    }
}
