<?php

declare(strict_types=1);

namespace Modules\Core\Search\Support;

use Closure;

/**
 * Wall-clock cost of the stages of one orchestrated search, read from the monotonic clock.
 *
 * Built only when the `search.debug_timings` setting (`core.search.debug_timings`) is on, so a
 * search with the switch off pays for nothing. A stage that never ran stays null rather than 0,
 * which keeps "skipped" apart from "too fast to see".
 */
final class SearchStageTimings
{
    public const string INTENT = 'intent';

    public const string PLAN = 'plan';

    public const string VECTOR = 'vector';

    public const string ENSEMBLE = 'ensemble';

    /**
     * @var array{intent: float|null, plan: float|null, vector: float|null, ensemble: float|null}
     */
    private array $stages = [
        self::INTENT => null,
        self::PLAN => null,
        self::VECTOR => null,
        self::ENSEMBLE => null,
    ];

    private readonly int $started_at;

    public function __construct()
    {
        $this->started_at = hrtime(true);
    }

    /**
     * Runs the stage and records its duration, also when it throws: the exception is left untouched.
     *
     * @template TResult
     *
     * @param  self::INTENT|self::PLAN|self::VECTOR|self::ENSEMBLE  $stage
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function measure(string $stage, Closure $callback): mixed
    {
        $stage_started_at = hrtime(true);

        try {
            return $callback();
        } finally {
            $this->stages[$stage] = self::milliseconds(hrtime(true) - $stage_started_at);
        }
    }

    /**
     * @return array{intent_ms: float|null, plan_ms: float|null, vector_ms: float|null, ensemble_ms: float|null, total_ms: float}
     */
    public function toMeta(): array
    {
        return [
            'intent_ms' => $this->stages[self::INTENT],
            'plan_ms' => $this->stages[self::PLAN],
            'vector_ms' => $this->stages[self::VECTOR],
            'ensemble_ms' => $this->stages[self::ENSEMBLE],
            'total_ms' => self::milliseconds(hrtime(true) - $this->started_at),
        ];
    }

    private static function milliseconds(int $nanoseconds): float
    {
        return round($nanoseconds / 1_000_000, 3);
    }
}
