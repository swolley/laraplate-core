<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Actions\Users\GetUserInfoAction;
use Modules\Core\Actions\Users\HandleSocialLoginAction;
use Modules\Core\Actions\Users\ImpersonateUserAction;
use Modules\Core\Actions\Users\LeaveImpersonationAction;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Http\Requests\ImpersonationRequest;
use Modules\Core\Http\Requests\UpdatePreferencesRequest;
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

    public function userInfo(Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        // questo riassegna una licenza all'utente in sessione se da comando si è fatto un aggiornamento delle licenze che ha disassociato i riferimenti
        try {
            return new ResponseBuilder($request)
                ->setData(($this->getUserInfoAction)($user))
                ->json();
        } catch (AuthorizationException $unauthorizedException) {
            return new ResponseBuilder($request)
                ->setError($unauthorizedException->getMessage())
                ->setStatus(Response::HTTP_UNAUTHORIZED)
                ->json();
        }
    }

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

    public function maintainSession(): \Illuminate\Http\JsonResponse
    {
        return Auth::user()
            ? response()->json(['message' => 'Session maintained successfully.'])
            : response()->json(['error' => 'Unauthorized'], 401);
    }

    /**
     * Persist the caller's own UI preferences and echo back the refreshed profile.
     */
    public function updatePreferences(UpdatePreferencesRequest $request): \Illuminate\Http\JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Self-service write on the caller's own row: bypass the CRUD `update`
        // authorization + versioning events, which gate admins editing other users.
        $user->forceFill(['preferences' => $request->validated()['preferences']])->saveQuietly();

        return new ResponseBuilder($request)
            ->setData(($this->getUserInfoAction)($user))
            ->json();
    }

    /**
     * Mark the caller's onboarding flow as done and echo back the refreshed profile.
     */
    public function completeFirstLogin(Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $user->forceFill(['is_first_login' => false])->saveQuietly();

        return new ResponseBuilder($request)
            ->setData(($this->getUserInfoAction)($user))
            ->json();
    }
}
