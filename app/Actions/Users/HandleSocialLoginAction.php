<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Users;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\User as SocialUser;
use Modules\Core\Events\SocialLoginCompleted;

final class HandleSocialLoginAction
{
    /**
     * @param  callable(SocialUser,string):Authenticatable  $userUpserter
     */
    public function __construct(
        private readonly SocialiteFactory $socialite,
        private readonly mixed $userUpserter = null,
    ) {
    }

    public function redirect(string $service): RedirectResponse
    {
        return $this->socialite->driver($service)->redirect();
    }

    public function __invoke(string $service): Redirector|RedirectResponse
    {
        /** @var SocialUser $socialUser */
        $socialUser = $this->socialite->driver($service)->user();

        $user = $this->userUpserter
            ? ($this->userUpserter)($socialUser, $service)
            : user_class()::query()->updateOrCreate([
                'social_id' => $socialUser->getId(),
            ], [
                'name' => $socialUser->getName(),
                'username' => $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                'social_service' => $service,
                'social_token' => $socialUser->token,
                'social_refresh_token' => $socialUser->refreshToken ?? null,
                'social_token_secret' => $socialUser->tokenSecret ?? null,
            ]);

        Auth::login($user);

        SocialLoginCompleted::dispatch($user, $service);

        return redirect('/dashboard');
    }
}

