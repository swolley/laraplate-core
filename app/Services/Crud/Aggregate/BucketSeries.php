<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\Aggregate;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Expression;
use Modules\Core\Enums\TimeBucket;
use Modules\Core\Exceptions\AggregateException;
use Modules\Core\Support\DayTruncation;
use UnexpectedValueException;

/**
 * The series of a request grouped by a time bucket (spec 4.3, 4.4): one query grouped by the day of the field
 * of the period returns the partials of each measure per day, and PHP rolls the days up into the buckets of the
 * period with {@see BucketRollup}.
 *
 * Per day a `count` reads `count(*)` or `count(field)`, a `sum` the sum, an `avg` the sum and `count(field)`, a
 * `min` or `max` the value, so an average over a bucket is weighted by the rows of each day, not an average of
 * averages. Every bucket of the period is in the series: one with no row has 0 for `count` and `sum` and null for
 * `avg`, `min` and `max` (contract 5.1). A bucket is keyed by the date of its first day and has no label.
 */
final class BucketSeries
{
    private const string DAY_ALIAS = 'bucket_day';

    private const string COUNT_SUFFIX = '_count';

    /**
     * The series of the rows of the scope over the period, cut into the bucket, in the order of the sort.
     *
     * The rows are those of the scope: narrow it to the period first ({@see PeriodPlan::narrow()}). The day of a row
     * is read from the first ten characters of the day expression (Oracle's `TRUNC` gives a date-time), as a day of
     * the application time zone; a day outside the buckets of the period, which a stored value with a time zone of
     * its own could give, is left out of the series.
     *
     * @throws AggregateException
     *
     * @return list<array{key: string, label: null, values: array<string, int|float|string|null>}>
     */
    public static function read(AggregateScope $scope, AggregateQuery $query, AggregatePeriod $period, TimeBucket $bucket): array
    {
        $days = self::days($scope, $query->measures, $period->field, $bucket);
        $series = [];

        foreach ($period->buckets($bucket) as $start) {
            $key = $bucket->key($start);
            $series[] = ['key' => $key, 'label' => null, 'values' => self::values($query->measures, $days[$key] ?? [])];
        }

        return self::sorted($series, $query);
    }

    /**
     * The partials of each day the rows hold, under the key of the bucket the day falls in.
     *
     * @param  list<AggregateMeasure>  $measures
     *
     * @throws AggregateException
     *
     * @return array<string, list<array<array-key, mixed>>>
     */
    private static function days(AggregateScope $scope, array $measures, string $field, TimeBucket $bucket): array
    {
        $model = $scope->model;
        $rows = ($scope->baseQuery)()->toBase()->reorder();
        $grammar = $rows->getGrammar();
        $day = DayTruncation::expression($model->getConnection()->getDriverName(), $grammar->wrap($model->qualifyColumn($field)));

        $select = [new Expression($day . ' as ' . $grammar->wrap(self::DAY_ALIAS))];

        foreach ($measures as $index => $measure) {
            $function = MeasureSql::functionOf($measure, $index);
            $column = MeasureSql::column($model, $grammar, $measure, $index) ?? '*';
            $alias = MeasureSql::alias($index);

            $select[] = new Expression(($function === 'avg' ? 'sum' : $function) . '(' . $column . ') as ' . $grammar->wrap($alias));

            if ($function === 'avg') {
                $select[] = new Expression('count(' . $column . ') as ' . $grammar->wrap($alias . self::COUNT_SUFFIX));
            }
        }

        $timezone = config()->string('app.timezone');
        $days = [];

        foreach ($rows->select($select)->groupByRaw($day)->get() as $row) {
            $row = (array) $row;
            $key = self::bucketKey($row[self::DAY_ALIAS] ?? null, $bucket, $timezone);

            if ($key !== null) {
                $days[$key][] = $row;
            }
        }

        return $days;
    }

    /**
     * The key of the bucket that holds the day the database gave, or null when it gave no day.
     */
    private static function bucketKey(mixed $day, TimeBucket $bucket, string $timezone): ?string
    {
        $parts = [];

        if (! is_string($day) || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $day, $parts) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromDate((int) $parts[1], (int) $parts[2], (int) $parts[3], $timezone)->startOfDay();

        return $bucket->key($bucket->start($date));
    }

    /**
     * The figures of a bucket from the partials of its days.
     *
     * @param  list<AggregateMeasure>  $measures
     * @param  list<array<array-key, mixed>>  $days
     *
     * @throws AggregateException
     *
     * @return array<string, int|float|string|null>
     */
    private static function values(array $measures, array $days): array
    {
        $values = [];

        foreach ($measures as $index => $measure) {
            $alias = MeasureSql::alias($index);
            $partials = array_map(static fn (array $day): ?string => self::partial($day[$alias] ?? null), $days);

            $values[$measure->name()] = match (MeasureSql::functionOf($measure, $index)) {
                'count' => self::count($partials),
                'sum' => $days === [] ? 0 : MeasureSql::figure(BucketRollup::sum($partials)),
                'avg' => MeasureSql::figure(BucketRollup::avg(
                    BucketRollup::sum($partials),
                    self::count(array_map(static fn (array $day): ?string => self::partial($day[$alias . self::COUNT_SUFFIX] ?? null), $days)),
                )),
                'min' => MeasureSql::figure(BucketRollup::min($partials)),
                'max' => MeasureSql::figure(BucketRollup::max($partials)),
            };
        }

        return $values;
    }

    /**
     * A partial as the text of its value: an integer as its digits and a float in the shortest form that reads back
     * as the same float, so the roll-up adds what the database computed.
     */
    private static function partial(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_string($value) => $value,
            is_int($value) => (string) $value,
            is_float($value) && is_finite($value) => var_export($value, true),
            default => throw new UnexpectedValueException(sprintf('An aggregate function returned %s, which is neither a number nor a text.', get_debug_type($value))),
        };
    }

    /**
     * The sum of the partial counts of the days.
     *
     * @param  list<?string>  $partials
     */
    private static function count(array $partials): int
    {
        return (int) BucketRollup::sum($partials);
    }

    /**
     * The series in the order of the sort: by key it is chronological, or the reverse; by measure it follows the
     * first measure, a null value coming first in ascending order, and a tie keeps the chronological order.
     *
     * @param  list<array{key: string, label: null, values: array<string, int|float|string|null>}>  $series
     * @return list<array{key: string, label: null, values: array<string, int|float|string|null>}>
     */
    private static function sorted(array $series, AggregateQuery $query): array
    {
        $direction = $query->sortDir === 'desc' ? -1 : 1;

        if ($query->sortBy === 'key') {
            return $direction === 1 ? $series : array_reverse($series);
        }

        $name = ($query->measures[0] ?? null)?->name();

        if ($name === null) {
            return $series;
        }

        usort($series, static fn (array $first, array $second): int => self::compare($first['values'][$name] ?? null, $second['values'][$name] ?? null) * $direction);

        return $series;
    }

    private static function compare(int|float|string|null $first, int|float|string|null $second): int
    {
        if ($first === null || $second === null) {
            return ($first !== null) <=> ($second !== null);
        }

        if (is_string($first) || is_string($second)) {
            return strcmp((string) $first, (string) $second);
        }

        return $first <=> $second;
    }
}
