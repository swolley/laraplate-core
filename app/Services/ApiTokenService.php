<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use function user_class;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Modules\Core\Authorization\ResolvingAuthorization;
use Modules\Core\Models\Permission;
use Modules\Core\Models\User;
use Modules\Core\Rules\NetworkAddress;
use UnexpectedValueException;

/**
 * Issues and describes the API tokens of an account, for the self-service endpoints and for the backoffice.
 *
 * A token carries abilities, which are `api` permission names. They have to be a subset of what the
 * account already holds on the `api` guard (through its roles, ancestor roles included, or directly), the
 * wildcard is refused, and at use a request needs the role permission and the ability both. A personal
 * token always expires, within `core.api_tokens.max_lifetime_days`; the token of a service account may be
 * non-expiring. A superadmin never gets a token (and one that exists is refused at use).
 */
final class ApiTokenService
{
    public const string WILDCARD_ABILITY = '*';

    private const string API_GUARD = 'api';

    /**
     * The `api` permission names the account holds, sorted: the abilities a token for it may carry.
     *
     * @return list<string>
     */
    public function grantableAbilities(User $user): array
    {
        return ResolvingAuthorization::resolve(function () use ($user): array {
            $permissions = [];

            foreach ($user->roles as $role) {
                if ($role->guard_name !== self::API_GUARD) {
                    continue;
                }

                foreach ($role->getAllPermissions() as $permission) {
                    $permissions[] = $permission;
                }
            }

            foreach ($user->getDirectPermissions() as $permission) {
                $permissions[] = $permission;
            }

            $names = [];

            foreach ($permissions as $permission) {
                if ($permission instanceof Permission && $permission->guard_name === self::API_GUARD) {
                    $names[] = $permission->name;
                }
            }

            $names = array_values(array_unique($names));
            sort($names);

            return $names;
        });
    }

    /**
     * The longest life of a personal token, in days.
     */
    public function maxLifetimeDays(): int
    {
        return max(1, config()->integer('core.api_tokens.max_lifetime_days', 365));
    }

    /**
     * Creates a token for the account after checking every rule above.
     *
     * The token is made from the model the auth provider hands out: Sanctum only accepts tokens whose
     * tokenable is an instance of that class, so a token made from a parent class would never authenticate.
     *
     * @param  list<string>  $abilities
     * @param  list<string>|null  $allowed_cidrs  `null` or empty means no restriction
     *
     * @throws ValidationException when the abilities, the expiry or a network are not acceptable
     */
    public function issue(User $user, string $name, array $abilities, ?CarbonInterface $expires_at, ?array $allowed_cidrs): NewAccessToken
    {
        $tokenable = $this->tokenable($user);

        abort_if($tokenable->isSuperAdmin(), 403, 'A superadmin cannot have API tokens.');

        $this->assertAbilities($tokenable, $abilities);
        $this->assertExpiry($tokenable, $expires_at);
        $networks = $this->normalizedNetworks($allowed_cidrs);

        $token = $tokenable->createToken($name, $abilities, $expires_at);

        if ($networks !== []) {
            $token->accessToken->forceFill(['allowed_cidrs' => json_encode($networks)])->save();
        }

        return $token;
    }

    /**
     * The account as the model the auth provider hands out.
     */
    public function tokenable(User $user): User
    {
        $class = user_class();

        if ($user instanceof $class) {
            return $user;
        }

        $found = $class::query()->whereKey($user->getKey())->firstOrFail();

        if (! $found instanceof User) {
            throw new UnexpectedValueException('The configured user model must extend ' . User::class . '.');
        }

        return $found;
    }

    /**
     * @param  list<string>  $abilities
     */
    private function assertAbilities(User $tokenable, array $abilities): void
    {
        if ($abilities === []) {
            throw ValidationException::withMessages(['abilities' => 'A token needs at least one ability.']);
        }

        if (in_array(self::WILDCARD_ABILITY, $abilities, true)) {
            throw ValidationException::withMessages(['abilities' => 'The wildcard ability is not accepted: list the permissions the token may use.']);
        }

        $foreign = array_values(array_diff($abilities, $this->grantableAbilities($tokenable)));

        if ($foreign !== []) {
            throw ValidationException::withMessages([
                'abilities' => 'The account does not hold these api permissions: ' . implode(', ', $foreign) . '.',
            ]);
        }
    }

    private function assertExpiry(User $tokenable, ?CarbonInterface $expires_at): void
    {
        if ($expires_at instanceof CarbonInterface && $expires_at->isPast()) {
            throw ValidationException::withMessages(['expires_at' => 'The expiry must be in the future.']);
        }

        if ($tokenable->isServiceAccount()) {
            return;
        }

        if (! $expires_at instanceof CarbonInterface) {
            throw ValidationException::withMessages(['expires_at' => 'A personal token must expire.']);
        }

        if ($expires_at->greaterThan(Date::now()->addDays($this->maxLifetimeDays()))) {
            throw ValidationException::withMessages([
                'expires_at' => 'A personal token cannot live longer than ' . $this->maxLifetimeDays() . ' days.',
            ]);
        }
    }

    /**
     * @param  list<string>|null  $allowed_cidrs
     * @return list<string>
     */
    private function normalizedNetworks(?array $allowed_cidrs): array
    {
        $networks = array_values(array_unique($allowed_cidrs ?? []));

        foreach ($networks as $index => $network) {
            if (! NetworkAddress::isValid($network)) {
                throw ValidationException::withMessages(["allowed_cidrs.{$index}" => 'This is not an address or a network in CIDR notation.']);
            }
        }

        return $networks;
    }
}
