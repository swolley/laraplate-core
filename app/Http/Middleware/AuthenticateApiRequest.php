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
 * from the token alone). Failed bearer authentications (unknown, malformed, expired, revoked,
 * superadmin, address outside the allowed networks) are counted per client address, and an
 * address over the limit gets 429 before the token is looked up; successful requests do not
 * consume that budget.
 *
 * A request that claims no bearer (no `Authorization` header, or none that mentions one) runs as
 * the anonymous user, set directly on the guard; the Sanctum guard is only ever asked for a user
 * through {@see self::authenticateToken()}, so no token can be resolved outside its checks. A
 * header counts as a bearer claim when Laravel reads a token from it, wherever `Bearer ` sits, or
 * when it mentions a bearer at all.
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
        } elseif ($this->claimsBearer($request)) {
            $this->refuseWhenTooManyFailures($request);
            $user = $this->authenticateToken($request);
        } else {
            // Never through $request->user(): the Sanctum guard behind it would resolve any token it
            // can read, outside the checks of authenticateToken().
            $user = $this->authorization->resolveAnonymousUser($request);
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

    private function failureKey(Request $request): string
    {
        return 'api-bearer-failures:' . $request->ip();
    }

    /**
     * Refuses a bearer request, ahead of the token lookup, from an address that failed to authenticate
     * more than the limit allows in the current window.
     */
    private function refuseWhenTooManyFailures(Request $request): void
    {
        $key = $this->failureKey($request);

        if (RateLimiter::tooManyAttempts($key, config()->integer('core.api.rate_limit_per_minute', 600))) {
            abort(429, 'Too Many Attempts.', ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }
    }

    /**
     * Counts a failed bearer authentication against the client address, then refuses the request.
     *
     * @param  array<string, string>  $headers
     */
    private function failAuthentication(Request $request, int $status, string $message, array $headers = []): never
    {
        RateLimiter::hit($this->failureKey($request), 60);

        abort($status, $message, $headers);
    }

    /**
     * Whether the request claims a bearer credential, valid or not: either Laravel (and so Sanctum)
     * reads a token from the header, wherever `Bearer ` sits in it, or the header mentions a bearer at
     * all. A malformed claim is therefore refused as unauthenticated and never served as anonymous.
     */
    private function claimsBearer(Request $request): bool
    {
        if ($request->bearerToken() !== null) {
            return true;
        }

        $header = $request->headers->get('Authorization');

        return is_string($header) && mb_stripos($header, 'bearer') !== false;
    }

    /**
     * The tokenable behind the bearer token, or an abort.
     */
    private function authenticateToken(Request $request): User
    {
        $user = $this->resolveTokenUser();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if (! $user instanceof User || ! $token instanceof PersonalAccessToken || $user->isSuperAdmin()) {
            $this->failAuthentication($request, 401, 'Unauthenticated.', ['WWW-Authenticate' => 'Bearer']);
        }

        if (! $this->isAddressAllowed($token, $request->ip())) {
            // The token itself is a credential: only its id and the address are logged.
            Log::warning('API request refused: client address outside the networks of the token', [
                'token_id' => $token->getKey(),
                'tokenable_id' => $user->getKey(),
                'ip' => $request->ip(),
            ]);

            $this->failAuthentication($request, 403, 'Forbidden');
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
