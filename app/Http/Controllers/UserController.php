<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Auth\AuthManager;
use Illuminate\Foundation\Auth\User;
use Illuminate\Database\DatabaseManager;
use Laravel\Socialite\Facades\Socialite;
use Modules\Core\Helpers\ResponseBuilder;
use Illuminate\Contracts\Cache\Repository;
use Modules\Core\Listeners\AfterLoginListener;
use Illuminate\Validation\UnauthorizedException;
use Modules\Core\Http\Resources\UserInfoResponse;
use Laravel\Socialite\Contracts\User as SocialUser;
use Modules\Core\Http\Requests\ImpersonationRequest;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

class UserController extends Controller
{
    public function __construct(
        Repository $cache,
        AuthManager $auth,
        DatabaseManager $db,
        protected Socialite $socialite
    ) {
        parent::__construct($cache, $auth, $db);
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
        return static::parseUserInfo();
    }

    /**
     * @route-comment
     * Route(path: 'app/auth/user/profile-information', name: 'core.auth.userInfo', methods: [GET, HEAD], middleware: [auth])
     */
    public function userInfo(Request $request): HttpFoundationResponse
    {
        /** @var User|null $user */
        $user = $this->auth->user();
        // questo riassegna una licenza all'utente in sessione se da comando si è fatto un aggiornamento delle licenze che ha disassociato i riferimenti
        try {
            if ($user) {
                AfterLoginListener::checkUserLicense($user);
            }
            return new ResponseBuilder($request)
                ->setData(static::parseUserInfo($user))
                ->json();
        } catch (UnauthorizedException $ex) {
            return new ResponseBuilder($request)
                ->setError($ex->getMessage())
                ->setStatus(Response::HTTP_UNAUTHORIZED)
                ->json();
        }
    }

    /**
     * @route-comment
     * Route(path: 'app/auth/impersonate', name: 'core.auth.impersonate', methods: [POST], middleware: [auth, can:impersonate])
     */
    public function impersonate(ImpersonationRequest $request): HttpFoundationResponse
    {
        $user_to_impersonate_id = $request->validated()['user'];
        $user_to_impersonate = user_class()::findOrFail($user_to_impersonate_id);
        /** @var User $current_user  */
        $current_user = $this->auth->user();
        $current_user->impersonate($user_to_impersonate);

        return new ResponseBuilder($request)
            ->setData(static::parseUserInfo())
            ->json();
    }

    /**
     * @route-comment
     * Route(path: 'app/auth/leave-impersonate', name: 'core.auth.leaveImpersonate', methods: [POST], middleware: [auth, can:impersonate])
     */
    public function leaveImpersonate(Request $request): HttpFoundationResponse
    {
        /** @var User $current_user */
        $current_user = $this->auth->user();
        $current_user->leaveImpersonation();

        return new ResponseBuilder($request)
            ->setData(static::parseUserInfo())
            ->json();
    }

    public function socialLoginRedirect(string $service): \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Http\RedirectResponse
    {
        return $this->socialite->driver($service)->redirect();
    }

    public function socialLoginCallback(string $service)
    {
        /** @var SocialUser $social_user */
        $social_user = $this->socialite->driver($service)->user();

        $user = User::updateOrCreate([
            'social_id' => $social_user->getId(),
        ], [
            'name' => $social_user->getName(),
            'username' => $social_user->getNickname(),
            'email' => $social_user->getEmail(),
            'social_service' => $service,
            'social_token' => $social_user->token,
            'social_refresh_token' => $social_user->refreshToken ?? null,
            'social_token_secret' => $social_user->tokenSecret ?? null,
        ]);

        $this->auth->login($user);

        return redirect('/dashboard');
    }

    /**
     * @route-comment
     * Route(path: 'app/auth/still-here', name: 'core.auth.maintainSession', methods: [GET, HEAD], middleware: [auth])
     */
    public function maintainSession(): \Illuminate\Http\JsonResponse
    {
        return $this->auth->user()
            ? response()->json(['message' => 'Session maintained successfully.'])
            : response()->json(['error' => 'Unauthorized'], 401);
    }
}
