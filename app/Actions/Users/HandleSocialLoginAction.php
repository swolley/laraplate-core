<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Users;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
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
     * A custom `$userUpserter` owns user creation, so the registration switch is its concern. The
     * default path creates a user only when `auth.registration.enabled` is on or the `social_id` is known.
     *
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

        $refusal = $this->userUpserter ? null : $this->refusalFor($socialUser);

        if ($refusal !== null) {
            return redirect('/admin')->withErrors(['social' => $refusal]);
        }

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

    /**
     * A social identity never attaches to an existing account by email, and a new one is created only
     * while registration is open.
     */
    private function refusalFor(SocialUser $socialUser): ?string
    {
        $users = user_class()::query();

        if (
            $users->where('email', $socialUser->getEmail())
                ->where(fn (Builder $query) => $query->whereNull('social_id')->orWhere('social_id', '!=', $socialUser->getId()))
                ->exists()
        ) {
            return 'User already registered with another account type';
        }

        if (! config('core.auth.registration.enabled') && ! user_class()::query()->where('social_id', $socialUser->getId())->exists()) {
            return 'Registration is disabled';
        }

        return null;
    }
}
