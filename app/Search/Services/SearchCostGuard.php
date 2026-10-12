<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

use Illuminate\Support\Facades\RateLimiter;

/**
 * The cost controls of a search that asks for more than `fast`: how many retries a caller may request, and how
 * many `deep` searches one person may start per minute. Both are decided per request and both degrade instead of
 * refusing, so a caller over the limit still gets results.
 *
 * The retry cap is the runtime setting `search.max_retries` and the rate is `search.deep_per_minute`, so an
 * operator can lower either without a deploy.
 */
final readonly class SearchCostGuard
{
    public const string RATE_LIMITED = 'rate_limited';

    private const string LIMITER = 'search-deep';

    public function maxRetries(): int
    {
        return max(0, (int) config('core.search.max_retries', 2));
    }

    public function clampRetries(int $requested): int
    {
        return min(max(0, $requested), $this->maxRetries());
    }

    /**
     * Counts one `deep` search against the person's allowance and says whether it fits. The key is the person,
     * not the route, because the cost follows them.
     */
    public function allowsDeep(string $person): bool
    {
        $per_minute = max(1, (int) config('core.search.deep_per_minute', 6));

        return RateLimiter::attempt(self::LIMITER . ':' . $person, $per_minute, static fn (): bool => true, 60) === true;
    }
}
