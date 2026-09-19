<?php

declare(strict_types=1);

namespace Modules\Core\Search;

use Closure;
use Throwable;

/**
 * Processes a list of items in adaptive batches, sizing each batch with an
 * AIMD (additive-increase / multiplicative-decrease) rule driven only by
 * latency and thrown exceptions — no knowledge of the backend. A batch that
 * succeeds quickly grows the next one; a slow or failed batch halves it and,
 * on failure, retries fewer items. A weak server therefore converges to small
 * batches and a strong one to large, without per-server tuning.
 *
 * The backend integration decides what a batch does: `$process` receives a
 * chunk and returns its results (or an empty list for a side-effecting write).
 * It stays driver-agnostic, so the same controller drives Elasticsearch,
 * Typesense, database or the embedding service.
 */
final class AdaptiveBatchController
{
    private readonly Closure $clock;

    private readonly Closure $sleeper;

    public function __construct(
        private readonly int $minBatch = 1,
        private readonly int $maxBatch = 128,
        private readonly float $targetLatencySeconds = 5.0,
        private readonly int $rampStep = 4,
        private readonly int $maxRetriesAtFloor = 3,
        private readonly float $backoffSeconds = 0.5,
        ?Closure $clock = null,
        ?Closure $sleeper = null,
    ) {
        $this->clock = $clock ?? static fn (): float => hrtime(true) / 1_000_000_000;
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            if ($seconds > 0.0) {
                usleep((int) ($seconds * 1_000_000));
            }
        };
    }

    /**
     * @template TItem
     * @template TResult
     *
     * @param  list<TItem>  $items
     * @param  Closure(list<TItem>): list<TResult>  $process  Handles one batch; throws on failure.
     * @param  Closure(TItem): int|null  $sizeOf  Optional per-item size estimate for a byte cap.
     * @return list<TResult>
     */
    public function run(array $items, Closure $process, ?int $maxBatchBytes = null, ?Closure $sizeOf = null): array
    {
        $results = [];
        $batch = $this->minBatch;
        $index = 0;
        $count = count($items);
        $failuresAtFloor = 0;

        while ($index < $count) {
            $end = $this->chunkEnd($items, $index, min($count, $index + $batch), $maxBatchBytes, $sizeOf);
            $chunk = array_slice($items, $index, $end - $index);

            $started_at = ($this->clock)();

            try {
                foreach ($process($chunk) as $result) {
                    $results[] = $result;
                }

                $index = $end;
                $failuresAtFloor = 0;
                $elapsed = ($this->clock)() - $started_at;

                $batch = $elapsed <= $this->targetLatencySeconds
                    ? min($this->maxBatch, $batch + $this->rampStep)
                    : max($this->minBatch, intdiv($batch, 2));
            } catch (Throwable $exception) {
                if ($batch <= $this->minBatch) {
                    $failuresAtFloor++;

                    if ($failuresAtFloor > $this->maxRetriesAtFloor) {
                        throw $exception;
                    }
                } else {
                    $batch = max($this->minBatch, intdiv($batch, 2));
                }

                ($this->sleeper)($this->backoffSeconds * ($failuresAtFloor > 0 ? $failuresAtFloor : 1));
            }
        }

        return $results;
    }

    /**
     * Trim a count-bounded chunk further so its estimated size stays within the
     * byte cap, always keeping at least one item so progress is guaranteed.
     *
     * @template TItem
     *
     * @param  list<TItem>  $items
     * @param  Closure(TItem): int|null  $sizeOf
     */
    private function chunkEnd(array $items, int $start, int $countEnd, ?int $maxBatchBytes, ?Closure $sizeOf): int
    {
        if ($maxBatchBytes === null || $sizeOf === null) {
            return $countEnd;
        }

        $bytes = 0;

        for ($i = $start; $i < $countEnd; $i++) {
            $bytes += $sizeOf($items[$i]);

            if ($bytes > $maxBatchBytes && $i > $start) {
                return $i;
            }
        }

        return $countEnd;
    }
}
