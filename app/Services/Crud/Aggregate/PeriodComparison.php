<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\Aggregate;

use Modules\Core\Enums\TimeBucket;
use Modules\Core\Exceptions\AggregateException;

/**
 * The `compare` of an aggregate result (spec 4.3): the same figures over the previous period of equal length.
 *
 * Every request with a comparison gets the total of the previous period; a bucketed one gets its series too,
 * built by the same rules as the current one (every bucket filled) but always in the order of the calendar,
 * whatever the sort of the request (ruling P16): pairing two series by position only means something on the
 * calendar axis. So `compare.series` is chronological, and `compare.series[i]` stands beside `series[i]` only
 * while `series` is chronological too, which is the default sort of a bucketed request. The engine does not
 * re-sort `series`: when the request sorts it by a measure, or by key descending, a consumer must align the two by
 * key, not by position. The two series are as long as their periods have buckets: a period that is not whole
 * months can start or end its previous one in a partial bucket more or less.
 */
final class PeriodComparison
{
    /**
     * The figures of the previous period of the plan over the rows of the scope, or null when the request does
     * not compare. The scope is the one of the request, not narrowed to its period. Its cells are checked for mixed
     * currencies like those of the result.
     *
     * @throws AggregateException
     *
     * @return array{total: array<string, int|float|string|null>, series?: list<array{key: string, label: null, values: array<string, int|float|string|null>}>}|null
     */
    public static function read(AggregateScope $scope, AggregateQuery $query, PeriodPlan $plan, CurrencyCheck $currency): ?array
    {
        if (! $plan->previous instanceof AggregatePeriod) {
            return null;
        }

        $rows = PeriodPlan::narrow($scope, $plan->previous);
        $compare = ['total' => MeasureSql::totals($rows, $query->measures, $currency, previous: true)['values']];

        if ($plan->bucket instanceof TimeBucket) {
            $compare['series'] = BucketSeries::chronological($rows, $query, $plan->previous, $plan->bucket, $currency);
        }

        return $compare;
    }
}
