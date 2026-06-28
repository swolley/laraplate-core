<?php

declare(strict_types=1);

namespace Modules\Core\Listeners;

use Illuminate\Auth\Events\Logout;
use Modules\Core\Models\User;

final class TrackUserLogout
{
    public function handle(Logout $event): void
    {
        /** @var User $user */
        $user = $event->user;
        // $sessionId = session()->getId();
        // Remove session from the database
        $user->license?->delete();
    }
}
