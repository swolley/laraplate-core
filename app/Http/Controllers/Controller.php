<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as RoutingController;
use Modules\Core\Models\User;
use Symfony\Component\HttpFoundation\Response;

abstract class Controller extends RoutingController
{
    public function __construct() {}

    /**
     * The status of a refused permission: 401 for the anonymous user (or nobody), who may log in or
     * send a token and try again, 403 for an authenticated principal (a session user or a token
     * user), for whom authenticating again changes nothing.
     */
    protected function authorizationFailureStatus(Request $request): int
    {
        $user = $request->user();
        $guest = config('permission.users.guest');

        if (! $user instanceof User) {
            return Response::HTTP_UNAUTHORIZED;
        }

        if (is_string($guest) && $guest !== '' && in_array($guest, [$user->getAttribute('name'), $user->getAttribute('username')], true)) {
            return Response::HTTP_UNAUTHORIZED;
        }

        return Response::HTTP_FORBIDDEN;
    }
}
