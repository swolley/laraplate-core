<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The one authentication and the one switch of every `/api` route, Core and modules.
 *
 * In order: the API switch (`core.expose_api`), the `api` guard, the bearer token or the
 * anonymous user, the network restriction of the token and the rate limit. A bearer
 * token that does not resolve is refused: it never falls back to the anonymous user.
 */
final readonly class AuthenticateApiRequest
{
    public function __construct(
        private AuthorizationService $authorization,
        private ThrottleRequests $throttle,
    ) {}

    /**
     * @param  Closure(Request): mixed  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        abort_unless(filter_var(config('core.expose_api', false), FILTER_VALIDATE_BOOLEAN), 403, 'Forbidden');

        Auth::shouldUse('api');
        // The guard memoizes its user; a request starts with no user of a previous one.
        Auth::guard('api')->forgetUser();

        $user = $this->hasBearerToken($request)
            ? $this->authenticateToken($request)
            : $this->authorization->resolveUser($request);

        if ($user instanceof User) {
            $request->setUserResolver(static fn (): User => $user);
        }

        return $this->throttle->handle($request, $next, 'api');
    }

    /**
     * Whether the request names a bearer credential, valid or not.
     */
    private function hasBearerToken(Request $request): bool
    {
        return preg_match('/^Bearer(\s|$)/i', mb_trim($request->header('Authorization', ''))) === 1;
    }

    /**
     * The tokenable behind the bearer token, or an abort.
     */
    private function authenticateToken(Request $request): User
    {
        $user = Auth::guard('api')->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if (! $user instanceof User || ! $token instanceof PersonalAccessToken) {
            abort(401, 'Unauthenticated.', ['WWW-Authenticate' => 'Bearer']);
        }

        if ($user->isSuperAdmin()) {
            abort(401, 'Unauthenticated.', ['WWW-Authenticate' => 'Bearer']);
        }

        if (! $this->isAddressAllowed($token, $request->ip())) {
            // The token itself is a credential: only its id and the address are logged.
            Log::warning('API request refused: client address outside the networks of the token', [
                'token_id' => $token->getKey(),
                'tokenable_id' => $user->getKey(),
                'ip' => $request->ip(),
            ]);

            abort(403, 'Forbidden');
        }

        return $user;
    }

    /**
     * No list, or an empty one, means no restriction. A list that cannot be read refuses every address.
     */
    private function isAddressAllowed(PersonalAccessToken $token, ?string $ip): bool
    {
        $raw = $token->getAttribute('allowed_cidrs');

        if ($raw === null || $raw === '') {
            return true;
        }

        $cidrs = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($cidrs)) {
            return false;
        }

        if ($cidrs === []) {
            return true;
        }

        return $ip !== null && IpUtils::checkIp($ip, array_values(array_filter($cidrs, is_string(...))));
    }
}
