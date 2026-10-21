<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\Aggregate;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Modules\Core\Enums\AggregateErrorCode;
use Modules\Core\Enums\TimeBucket;
use Modules\Core\Exceptions\AggregateException;
use Modules\Core\Support\ColumnKinds;
use Modules\Core\Support\DayTruncation;

/**
 * The period of an aggregate request resolved and checked before any query (spec 4.2, 4.4): the period asked
 * for, the previous one when the request compares, and the time bucket that cuts them.
 *
 * A period is an extra condition on the rows of the scope, a half-open range of the application time zone
 * (`field >= from 00:00:00 and field < to + 1 day`), so the ACL and the list filters still bound every figure.
 * A time bucket cuts the field of the period: with any other field the period would not bound the buckets.
 */
final readonly class PeriodPlan
{
    private const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    private function __construct(
        public AggregatePeriod $current,
        public ?AggregatePeriod $previous,
        public ?TimeBucket $bucket,
    ) {}

    /**
     * The period of the query against the moment of the request; null when the query has none.
     *
     * The limits are checked here, before any query: the period has at most `max_period_days` days, and each
     * series it gives (the current one and, with a comparison, the previous one) at most `max_points` buckets. So is
     * the driver of the connection, which must have a day expression for a time bucket ({@see DayTruncation}).
     *
     * @throws AggregateException INVALID_FIELD when the period is malformed or names no column of the model, a time
     *                            bucket or a comparison has no period, or the bucket is not on the field of the
     *                            period; UNSUPPORTED_FUNCTION, reported, when the driver has no day expression for
     *                            the bucket; PERIOD_TOO_LONG when the period is longer, or gives more buckets, than
     *                            the limits
     */
    public static function resolve(Model $model, AggregateQuery $query, AggregateLimits $limits, CarbonImmutable $now): ?self
    {
        $group = $query->group;
        $bucket = $group?->bucket;

        if ($query->period === null) {
            if ($bucket instanceof TimeBucket) {
                throw new AggregateException(AggregateErrorCode::InvalidField, 'A group by a time bucket needs a period to cut into buckets.', 'period');
            }

            if ($query->compare) {
                throw new AggregateException(AggregateErrorCode::InvalidField, 'A comparison needs a period to compare with.', 'compare');
            }

            return null;
        }

        $driver = $model->getConnection()->getDriverName();

        if ($bucket instanceof TimeBucket && ! DayTruncation::supports($driver)) {
            throw DayTruncation::unsupported($driver);
        }

        $current = AggregatePeriod::resolve($query->period, $now);

        if (! ColumnKinds::exists($model, $current->field)) {
            throw new AggregateException(
                AggregateErrorCode::InvalidField,
                sprintf('"%s" is not a column of the entity.', $current->field),
                'period.field',
            );
        }

        if ($group instanceof AggregateGroup && $bucket instanceof TimeBucket && $group->field !== $current->field) {
            throw new AggregateException(
                AggregateErrorCode::InvalidField,
                sprintf('A time bucket cuts the period: group by "%s", the field of the period.', $current->field),
                'group_by.field',
            );
        }

        if ($limits->maxPeriodDays < $current->days()) {
            throw new AggregateException(
                AggregateErrorCode::PeriodTooLong,
                sprintf('The period has %d days, more than %d: shorten it.', $current->days(), $limits->maxPeriodDays),
                'period',
            );
        }

        $plan = new self($current, $query->compare ? $current->previous() : null, $bucket);

        if ($bucket instanceof TimeBucket) {
            $plan->assertPoints($bucket, $limits);
        }

        return $plan;
    }

    /**
     * The scope narrowed to the rows of the period: the base query and the keys of its rows get the range, the
     * lookups of related entities stay as they are.
     */
    public static function narrow(AggregateScope $scope, AggregatePeriod $period): AggregateScope
    {
        $column = $scope->model->qualifyColumn($period->field);
        $from = $period->from->format(self::DATE_TIME_FORMAT);
        $until = $period->to->addDay()->format(self::DATE_TIME_FORMAT);
        $base_query = $scope->baseQuery;
        $filtered_ids = $scope->filteredIds;

        return new AggregateScope(
            model: $scope->model,
            baseQuery: static fn (): Builder => $base_query()->where($column, '>=', $from)->where($column, '<', $until),
            filteredIds: static fn (): BaseQueryBuilder => $filtered_ids()->where($column, '>=', $from)->where($column, '<', $until),
            visibleRelatedKeys: $scope->visibleRelatedKeys,
            resolveToOne: $scope->resolveToOne,
            resolveLabels: $scope->resolveLabels,
        );
    }

    /**
     * What a result says of its period: the resolved period and, for a bucketed request, the bucket.
     *
     * @return array{resolved_period: array{field: string, from: string, to: string, timezone: string}, bucket?: string}
     */
    public function meta(): array
    {
        $meta = ['resolved_period' => $this->current->toMeta()];

        if ($this->bucket instanceof TimeBucket) {
            $meta['bucket'] = $this->bucket->value;
        }

        return $meta;
    }

    /**
     * @throws AggregateException PERIOD_TOO_LONG on `group_by.bucket`, naming a coarser bucket that fits
     */
    private function assertPoints(TimeBucket $bucket, AggregateLimits $limits): void
    {
        foreach ($this->series() as $name => $period) {
            $count = count($period->buckets($bucket));

            if ($count > $limits->maxPoints) {
                throw new AggregateException(
                    AggregateErrorCode::PeriodTooLong,
                    sprintf('%s gives %d %s buckets, more than %d: %s.', $name, $count, $bucket->value, $limits->maxPoints, $this->remedy($bucket, $limits)),
                    'group_by.bucket',
                );
            }
        }
    }

    /**
     * The first bucket coarser than the one asked for whose series all fit the limit, or a shorter period when none does.
     */
    private function remedy(TimeBucket $bucket, AggregateLimits $limits): string
    {
        $cases = TimeBucket::cases();

        foreach (array_slice($cases, (int) array_search($bucket, $cases, true) + 1) as $coarser) {
            $fits = true;

            foreach ($this->series() as $period) {
                $fits = $fits && $limits->maxPoints >= count($period->buckets($coarser));
            }

            if ($fits) {
                return 'use a coarser bucket, such as ' . $coarser->value;
            }
        }

        return 'shorten the period';
    }

    /**
     * The periods the result has a series for, each under the name a message gives it.
     *
     * @return array<string, AggregatePeriod>
     */
    private function series(): array
    {
        $series = ['The period' => $this->current];

        if ($this->previous instanceof AggregatePeriod) {
            $series['The previous period'] = $this->previous;
        }

        return $series;
    }
}
