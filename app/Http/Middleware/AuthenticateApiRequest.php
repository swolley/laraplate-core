<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Models\User;
use Modules\Core\Services\Authorization\AuthorizationService;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The one authentication and the one switch of every `/api` route, Core and modules.
 *
 * In order: the API switch (`core.expose_api`), the `api` guard, the bearer token or the
 * anonymous user, the network restriction of the token and the rate limit. A bearer
 * token that does not resolve is refused: it never falls back to the anonymous user, and
 * it never falls back to a web session either (the principal of a bearer request comes
 * from the token alone). Requests that carry a bearer are counted per client address
 * before the token is looked up, so repeated invalid tokens end in 429.
 *
 * A route that authenticates its caller itself declares
 * `->middleware(SelfAuthenticatedApiRoute::class)`: the switch, the `api` guard and the
 * rate limit still apply, the bearer is not read as a Laraplate token, and the request runs
 * as the anonymous user until the route's own check authenticates it.
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

        if ($this->isSelfAuthenticated($request)) {
            $user = $this->authorization->resolveAnonymousUser($request);
        } elseif ($this->hasBearerToken($request)) {
            $this->throttleByAddress($request);
            $user = $this->authenticateToken($request);
        } else {
            $user = $this->authorization->resolveUser($request);
        }

        if ($user instanceof User) {
            $request->setUserResolver(static fn (): User => $user);
        }

        return $this->throttle->handle($request, $next, 'api');
    }

    private function isSelfAuthenticated(Request $request): bool
    {
        $route = $request->route();

        return $route instanceof Route
            && in_array(SelfAuthenticatedApiRoute::class, resolve(Router::class)->gatherRouteMiddleware($route), true);
    }

    /**
     * Counts every request that carries a bearer against the client address, ahead of the token lookup.
     */
    private function throttleByAddress(Request $request): void
    {
        $key = 'api-bearer:' . $request->ip();
        $max = config()->integer('core.api.rate_limit_per_minute', 600);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            abort(429, 'Too Many Attempts.', ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }

        RateLimiter::hit($key, 60);
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
        $user = $this->resolveTokenUser();
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
     * Asks the Sanctum guard for the token user with its stateful fallthrough switched off: Sanctum
     * looks at the web session first, and a bearer request must not borrow a session principal.
     */
    private function resolveTokenUser(): mixed
    {
        $stateful_guards = config('sanctum.guard');
        config(['sanctum.guard' => []]);

        try {
            return Auth::guard('api')->user();
        } finally {
            config(['sanctum.guard' => $stateful_guards]);
        }
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
