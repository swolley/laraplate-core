<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs\Middleware;

use Closure;
use Modules\Core\Support\SearchEngineAvailability;

/**
 * Makes a queued search job fail when the engine cannot be reached.
 *
 * Indexing tolerates an unreachable engine so that a domain write is never lost; a queued job is
 * not a domain write, it exists only to reach the engine. Left to degrade, it would log a warning,
 * skip the work and finish as completed, and nothing would be indexed while every dashboard stays
 * green. Under this middleware the exception reaches the queue instead, so the job is retried with
 * its backoff and, once the attempts are spent, lands among the failed jobs.
 */
final class FailWhenSearchEngineUnreachable
{
    /**
     * @param  Closure(object): mixed  $next
     */
    public function handle(object $job, Closure $next): mixed
    {
        return SearchEngineAvailability::strictly(static fn (): mixed => $next($job));
    }
}
