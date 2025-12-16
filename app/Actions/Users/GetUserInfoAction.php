<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Users;

use Illuminate\Foundation\Auth\User;
use Illuminate\Validation\UnauthorizedException;
use Modules\Core\Http\Resources\UserInfoResponse;
use Modules\Core\Listeners\AfterLoginListener;

final class GetUserInfoAction
{
    /**
     * @throws UnauthorizedException
     */
    public function __invoke(?User $user): UserInfoResponse
    {
        if ($user instanceof User) {
            AfterLoginListener::checkUserLicense($user);
        }

        return new UserInfoResponse($user);
    }
}
