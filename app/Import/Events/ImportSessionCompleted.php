<?php

declare(strict_types=1);

namespace Modules\Core\Import\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Core\Models\ImportSession;

/**
 * Fired when a bulk import finishes streaming every row (some rows may have failed
 * per-row). This is the seam the in-app notification tray listens on to notify the
 * user who started the import; until that surface exists it is simply unobserved.
 */
final class ImportSessionCompleted
{
    use Dispatchable;

    public function __construct(public readonly ImportSession $session) {}
}
