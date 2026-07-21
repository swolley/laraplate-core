<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\Models\OutboxEvent;

interface OutboxPublisher
{
    public function publish(OutboxEvent $event): void;
}
