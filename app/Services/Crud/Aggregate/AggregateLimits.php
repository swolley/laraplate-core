<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\Aggregate;

/**
 * The ceilings of one aggregate request and of the assistant tool built on it, read from `core.aggregate.*`.
 *
 * Every value is read as an integer, because `env()` hands the config strings, and a value below 1 or not
 * numeric falls back to its default so that a wrong setting never switches a limit off.
 */
final readonly class AggregateLimits
{
    private const int DEFAULT_MAX_MEASURES = 3;

    private const int DEFAULT_MAX_GROUPS = 50;

    private const int DEFAULT_MAX_POINTS = 400;

    private const int DEFAULT_MAX_PERIOD_DAYS = 1096;

    private const int DEFAULT_MAX_OBJECTS_PER_MESSAGE = 6;

    private const int DEFAULT_RATE_PER_MINUTE = 60;

    public function __construct(
        public int $maxMeasures,
        public int $maxGroups,
        public int $maxPoints,
        public int $maxPeriodDays,
        public int $maxObjectsPerMessage,
        public int $ratePerMinute,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            maxMeasures: self::configured('max_measures', self::DEFAULT_MAX_MEASURES),
            maxGroups: self::configured('max_groups', self::DEFAULT_MAX_GROUPS),
            maxPoints: self::configured('max_points', self::DEFAULT_MAX_POINTS),
            maxPeriodDays: self::configured('max_period_days', self::DEFAULT_MAX_PERIOD_DAYS),
            maxObjectsPerMessage: self::configured('max_objects_per_message', self::DEFAULT_MAX_OBJECTS_PER_MESSAGE),
            ratePerMinute: self::configured('rate_per_minute', self::DEFAULT_RATE_PER_MINUTE),
        );
    }

    /**
     * The four limits the capabilities publish to a client.
     *
     * @return array{max_measures: int, max_groups: int, max_points: int, max_period_days: int}
     */
    public function toArray(): array
    {
        return [
            'max_measures' => $this->maxMeasures,
            'max_groups' => $this->maxGroups,
            'max_points' => $this->maxPoints,
            'max_period_days' => $this->maxPeriodDays,
        ];
    }

    private static function configured(string $key, int $default): int
    {
        $value = config('core.aggregate.' . $key);

        if (is_numeric($value) && (int) $value >= 1) {
            return (int) $value;
        }

        return $default;
    }
}
