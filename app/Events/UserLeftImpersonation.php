<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UserLeftImpersonation
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly User $user)
    {
    }
}

