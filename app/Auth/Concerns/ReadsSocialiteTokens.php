<?php

declare(strict_types=1);

namespace Modules\Core\Auth\Concerns;

use Laravel\Socialite\Contracts\User as SocialUser;

/**
 * Reads the OAuth credentials off a Socialite user.
 *
 * {@see SocialUser} declares none of them: `token` and `refreshToken` belong to
 * the OAuth2 user, `token` and `tokenSecret` to the OAuth1 one, and a custom
 * provider may return an object of its own carrying any subset. Reading a field
 * straight off the interface therefore worked only for the drivers that happened
 * to define it and raised an undefined-property warning for the rest.
 */
trait ReadsSocialiteTokens
{
    /**
     * @return array{token: ?string, refresh_token: ?string, token_secret: ?string}
     */
    protected function socialiteTokens(SocialUser $user): array
    {
        return [
            'token' => self::stringProperty($user, 'token'),
            'refresh_token' => self::stringProperty($user, 'refreshToken'),
            'token_secret' => self::stringProperty($user, 'tokenSecret'),
        ];
    }

    /**
     * Socialite declares these properties without a type, so whatever the provider
     * put there reaches us as mixed, and the OAuth1 and OAuth2 users each declare
     * only their own.
     */
    private static function stringProperty(SocialUser $user, string $name): ?string
    {
        if (! property_exists($user, $name)) {
            return null;
        }

        $value = $user->{$name};

        return is_string($value) && $value !== '' ? $value : null;
    }
}
