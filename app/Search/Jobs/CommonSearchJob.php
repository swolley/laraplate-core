<?php

declare(strict_types=1);

namespace Modules\Core\Search\Jobs;

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

    public function __construct()
    {
        $this->onQueue(config('scout.queue.queue', 'indexing'));

        // Set job configurations from config
        $this->tries = config('scout.queue.tries', 3);
        $this->timeout = config('scout.queue.timeout', 120);
        $this->backoff = config('scout.queue.backoff', [30, 60, 180]);
    }

    public function middleware(): array
    {
        return [
            new RateLimited('indexing'),
        ];
    }
}
