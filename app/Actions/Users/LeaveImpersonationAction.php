<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Users;

use Illuminate\Foundation\Auth\User;
use Modules\Core\Events\UserLeftImpersonation;
use Modules\Core\Http\Resources\UserInfoResponse;

final class LeaveImpersonationAction
{
    public function __invoke(User $currentUser): UserInfoResponse
    {
        $currentUser->leaveImpersonation();

        UserLeftImpersonation::dispatch($currentUser);

        return new UserInfoResponse($currentUser);
    }
}

