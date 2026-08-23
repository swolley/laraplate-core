<?php

declare(strict_types=1);

namespace Modules\Core\Import\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Core\Models\ImportSession;

/**
 * Fired when a bulk import's processing job aborts before finishing the stream — an
 * unreadable file, an unregistered entity, an unexpected fatal. The seam for the
 * in-app notification tray to tell the user their import did not complete.
 */
final class ImportSessionFailed
{
    use Dispatchable;

    public function __construct(
        public readonly ImportSession $session,
        public readonly string $reason,
    ) {}
}
