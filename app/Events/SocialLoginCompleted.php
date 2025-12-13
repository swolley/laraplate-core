<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SocialLoginCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly string $service,
    ) {
    }
}

