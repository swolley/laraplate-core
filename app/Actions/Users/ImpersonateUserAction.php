<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Users;

use Illuminate\Foundation\Auth\User;
use Modules\Core\Events\UserImpersonated;
use Modules\Core\Http\Resources\UserInfoResponse;

final class ImpersonateUserAction
{
    public function __invoke(User $currentUser, User $targetUser): UserInfoResponse
    {
        $currentUser->impersonate($targetUser);

        UserImpersonated::dispatch($currentUser, $targetUser);

        return new UserInfoResponse($targetUser);
    }
}

