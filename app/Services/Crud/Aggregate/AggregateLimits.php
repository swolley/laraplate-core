<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\Aggregate;

use Modules\Core\Enums\AggregateErrorCode;
use Modules\Core\Exceptions\AggregateException;

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
     * Refuses more measures than one request may hold. The request validator and the engine both call it, so a
     * query built without the validator meets the same limit and the same message.
     *
     * @throws AggregateException INVALID_FIELD on `measures`
     */
    public function assertMeasureCount(int $count): void
    {
        if ($count > $this->maxMeasures) {
            throw new AggregateException(
                AggregateErrorCode::InvalidField,
                sprintf('An aggregate holds at most %d measures.', $this->maxMeasures),
                'measures',
            );
        }
    }

    /**
     * Refuses a limit of groups above the most a request may show, as {@see self::assertMeasureCount()} does for
     * the measures.
     *
     * @throws AggregateException TOO_MANY_GROUPS on `limit`
     */
    public function assertGroupLimit(int $limit): void
    {
        if ($limit > $this->maxGroups) {
            throw new AggregateException(
                AggregateErrorCode::TooManyGroups,
                sprintf('The limit is at most %d groups.', $this->maxGroups),
                'limit',
            );
        }
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
