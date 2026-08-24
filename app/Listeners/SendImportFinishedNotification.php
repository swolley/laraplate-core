<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Modules\Core\Import\Events\ImportSessionCompleted;
use Modules\Core\Import\Events\ImportSessionFailed;
use Modules\Core\Notifications\ImportFinishedNotification;

/**
 * Turns the terminal import events into an in-app notification for the user who
 * launched the import. A session with no owner (e.g. a system-run import) is
 * skipped rather than notified into the void.
 */
final class SendImportFinishedNotification
{
    public function handle(ImportSessionCompleted|ImportSessionFailed $event): void
    {
        $user = $event->session->user;

        if ($user === null) {
            return;
        }

        $failed = $event instanceof ImportSessionFailed;

        $user->notify(new ImportFinishedNotification(
            $event->session,
            failed: $failed,
            error: $failed ? $event->reason : null,
        ));
    }
}
