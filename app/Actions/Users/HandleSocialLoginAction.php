<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Users;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\User as SocialUser;
use Modules\Core\Auth\Concerns\ReadsSocialiteTokens;
use Modules\Core\Events\SocialLoginCompleted;

final readonly class HandleSocialLoginAction
{
    use ReadsSocialiteTokens;

    /**
     * @param  callable(SocialUser,string):Authenticatable  $userUpserter
     */
    public function __construct(
        private SocialiteFactory $socialite,
        private mixed $userUpserter = null,
    ) {}

    public function __invoke(string $service): Redirector|RedirectResponse
    {
        /** @var SocialUser $socialUser */
        $socialUser = $this->socialite->driver($service)->user();

        $tokens = $this->socialiteTokens($socialUser);

        $user = $this->userUpserter
            ? ($this->userUpserter)($socialUser, $service)
            : user_class()::query()->updateOrCreate([
                'social_id' => $socialUser->getId(),
            ], [
                'name' => $socialUser->getName(),
                'username' => $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                'social_service' => $service,
                'social_token' => $tokens['token'],
                'social_refresh_token' => $tokens['refresh_token'],
                'social_token_secret' => $tokens['token_secret'],
            ]);

        Auth::login($user);

        event(new SocialLoginCompleted($user, $service));

        return redirect('/admin');
    }

    public function redirect(string $service): RedirectResponse
    {
        return $this->socialite->driver($service)->redirect();
    }
}
