<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\UnauthorizedException;
use Modules\Core\Actions\Users\GetUserInfoAction;
use Modules\Core\Actions\Users\HandleSocialLoginAction;
use Modules\Core\Actions\Users\ImpersonateUserAction;
use Modules\Core\Actions\Users\LeaveImpersonationAction;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Http\Requests\ImpersonationRequest;
use Modules\Core\Http\Resources\UserInfoResponse;

final class UserController extends Controller
{
    public function __construct(
        private readonly GetUserInfoAction $getUserInfoAction,
        private readonly ImpersonateUserAction $impersonateUserAction,
        private readonly LeaveImpersonationAction $leaveImpersonationAction,
        private readonly HandleSocialLoginAction $handleSocialLoginAction,
    ) {
        parent::__construct();
    }

    /**
     * @return array<array<mixed|array<string>>|false|int|mixed|string>
     *
     * @psalm-return array{id: 'anonymous'|int, name: string, username: string, email: string, groups: array<int, mixed>, canImpersonate: false|mixed, permissions: array<list<string>>}
     */
    public static function parseUserInfo(?User $user = null): UserInfoResponse
    {
        return new UserInfoResponse($user);
    }

    public static function parseAnonymousUserInfo(): array
    {
        return self::parseUserInfo();
    }

    /**
     * @route-comment
     * Route(path: 'app/auth/user/profile-information', name: 'core.auth.userInfo', methods: [GET, HEAD], middleware: [auth])
     */
    public function userInfo(Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        // questo riassegna una licenza all'utente in sessione se da comando si è fatto un aggiornamento delle licenze che ha disassociato i riferimenti
        try {
            return new ResponseBuilder($request)
                ->setData(($this->getUserInfoAction)($user))
                ->json();
        } catch (UnauthorizedException $unauthorizedException) {
            return new ResponseBuilder($request)
                ->setError($unauthorizedException->getMessage())
                ->setStatus(Response::HTTP_UNAUTHORIZED)
                ->json();
        }
    }

    /**
     * @route-comment
     * Route(path: 'app/auth/impersonate', name: 'core.auth.impersonate', methods: [POST], middleware: [auth, can:impersonate])
     */
    public function impersonate(ImpersonationRequest $request): \Illuminate\Http\JsonResponse
    {
        $user_to_impersonate_id = $request->validated()['user'];
        $user_to_impersonate = user_class()::query()->findOrFail($user_to_impersonate_id);

        /** @var User $current_user */
        $current_user = Auth::user();

        return new ResponseBuilder($request)
            ->setData(($this->impersonateUserAction)($current_user, $user_to_impersonate))
            ->json();
    }

    /**
     * @route-comment
     * Route(path: 'app/auth/leave-impersonate', name: 'core.auth.leaveImpersonate', methods: [POST], middleware: [auth, can:impersonate])
     */
    public function leaveImpersonate(Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var User $current_user */
        $current_user = Auth::user();

        return new ResponseBuilder($request)
            ->setData(($this->leaveImpersonationAction)($current_user))
            ->json();
    }

    public function socialLoginRedirect(string $service): \Symfony\Component\HttpFoundation\RedirectResponse|RedirectResponse
    {
        return $this->handleSocialLoginAction->redirect($service);
    }

    public function socialLoginCallback(string $service): Redirector|RedirectResponse
    {
        return ($this->handleSocialLoginAction)($service);
    }

    /**
     * @route-comment
     * Route(path: 'app/auth/still-here', name: 'core.auth.maintainSession', methods: [GET, HEAD], middleware: [auth])
     */
    public function maintainSession(): \Illuminate\Http\JsonResponse
    {
        return Auth::user()
            ? response()->json(['message' => 'Session maintained successfully.'])
            : response()->json(['error' => 'Unauthorized'], 401);
    }
}
