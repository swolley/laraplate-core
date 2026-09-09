<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs;

use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;

abstract class CommonSearchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximum number of job attempts.
     */
    public int $tries;

    /**
     * Job timeout in seconds.
     */
    public int $timeout;

    /**
     * Backoff time between attempts (in seconds).
     */
    public array $backoff;

    /**
     * Unhandled exceptions allowed before the job fails. Rate-limit releases are
     * not exceptions, so they do not consume this budget.
     */
    public int $maxExceptions;

    public function __construct()
    {
        $this->onQueue(config('scout.queue.queue', 'indexing'));

        // Set job configurations from config
        $this->tries = config('scout.queue.tries', 3);
        $this->timeout = config('scout.queue.timeout', 120);
        $this->backoff = config('scout.queue.backoff', [30, 60, 180]);
        $this->maxExceptions = config('scout.queue.max_exceptions', 3);
    }

    /**
     * Time-based retry bound. It takes precedence over the tries count (including
     * Horizon's supervisor `tries`), so a job repeatedly released by the
     * `indexing` rate limiter during a mass backfill waits for its slot instead
     * of dying with MaxAttemptsExceeded. Real errors are still bounded by
     * $maxExceptions.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes((int) config('scout.queue.retry_until_minutes', 720));
    }

    public function middleware(): array
    {
        return [
            new RateLimited('indexing'),
        ];
    }
}
