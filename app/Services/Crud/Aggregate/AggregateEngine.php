<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\Aggregate;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Database\Query\Expression;
use Modules\Core\Enums\AggregateErrorCode;
use Modules\Core\Enums\TimeBucket;
use Modules\Core\Exceptions\AggregateException;
use Modules\Core\Support\ColumnKinds;

/**
 * Runs an {@see AggregateQuery} over the rows of an {@see AggregateScope} and returns the {@see AggregateResult} of
 * the contract.
 *
 * Every figure is computed by SQL over the base query of the scope, so the row-level ACL and the list filters
 * bound it. No text of the request reaches SQL: a field is used only once it is a column of the model, or of the
 * model a to-one relation reaches ({@see ColumnKinds::exists()}), and then qualified by its table and wrapped by
 * the grammar of the connection; a function is one of a closed list; a figure is selected under a positional
 * alias (`m0`, `m1`, ...) and named after its measure in PHP ({@see MeasureSql}).
 *
 * The engine dispatches a request to its path: ungrouped, grouped by the value of a column (here), or grouped by a
 * time bucket ({@see BucketSeries}). A period ({@see PeriodPlan}) is resolved and checked before any query and
 * narrows the rows of every path; a comparison ({@see PeriodComparison}) runs the same total, and for a bucket the
 * same series, over the previous period.
 *
 * @phpstan-type Figures array{
 *     series: list<array{key: mixed, label: ?string, values: array<string, int|float|string|null>}>,
 *     total: array<string, int|float|string|null>,
 *     other: array<string, mixed>|null,
 *     row_count: int,
 *     group_count: int,
 *     truncated: bool,
 * }
 */
final class AggregateEngine
{
    private const string GROUP_KEY_ALIAS = 'group_key';

    private const string DISTINCT_KEYS_ALIAS = 'distinct_keys';

    private const string NULL_KEY_ROWS_ALIAS = 'null_key_rows';

    public function __construct(private readonly AggregateLimits $limits) {}

    /**
     * The limits are applied here too, because an {@see AggregateQuery} can be built without
     * {@see AggregateQuery::fromArray()}, which applies them to a request.
     *
     * @throws AggregateException when the query holds more measures or asks for more groups than the limits, a measure is not a function of the closed list or names a field that is not a column of the model, the group field is not a column the engine can group by, or the period is malformed or too long for the limits
     * @throws AuthorizationException when the caller may not select the related entity of a relation group or of a group label, which the scope reads as the facets do
     */
    public function run(AggregateScope $scope, AggregateQuery $query, CarbonImmutable $now): AggregateResult
    {
        $this->limits->assertMeasureCount(count($query->measures));

        if ($query->limit !== null) {
            $this->limits->assertGroupLimit($query->limit);
        }

        $plan = PeriodPlan::resolve($scope->model, $query, $this->limits, $now);
        $rows = $plan instanceof PeriodPlan ? PeriodPlan::narrow($scope, $plan->current) : $scope;

        if ($plan instanceof PeriodPlan && $plan->bucket instanceof TimeBucket) {
            $figures = $this->bucketed($rows, $query, $plan->current, $plan->bucket);
        } elseif ($query->group instanceof AggregateGroup) {
            $figures = $this->grouped($rows, $query, $query->group);
        } else {
            $figures = $this->ungrouped($rows, $query);
        }

        return new AggregateResult(
            series: $figures['series'],
            total: $figures['total'],
            other: $figures['other'],
            compare: $plan instanceof PeriodPlan ? PeriodComparison::read($scope, $query, $plan) : null,
            meta: [
                ...($plan instanceof PeriodPlan ? $plan->meta() : []),
                'row_count' => $figures['row_count'],
                'group_count' => $figures['group_count'],
                'truncated' => $figures['truncated'],
                'generated_at' => $now->toIso8601ZuluString(),
            ],
        );
    }

    /**
     * The text a label holds: a key read from the database, or the label read for it.
     */
    private static function text(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private static function unknownGroup(string $field): AggregateException
    {
        return new AggregateException(
            AggregateErrorCode::InvalidField,
            sprintf('"%s" is not a column of the entity or of a to-one relation of it.', $field),
            'group_by.field',
        );
    }

    /**
     * The total over every row of the scope, with no series.
     *
     * @throws AggregateException
     *
     * @return Figures
     */
    private function ungrouped(AggregateScope $scope, AggregateQuery $query): array
    {
        ['values' => $total, 'row_count' => $row_count] = MeasureSql::totals($scope, $query->measures);

        return ['series' => [], 'total' => $total, 'other' => null, 'row_count' => $row_count, 'group_count' => 0, 'truncated' => false];
    }

    /**
     * The buckets of the period (spec 4.3): every bucket is shown, so the series is never truncated and the group
     * count is the number of buckets; the total and the row count are those of the rows in the period.
     *
     * @throws AggregateException
     *
     * @return Figures
     */
    private function bucketed(AggregateScope $scope, AggregateQuery $query, AggregatePeriod $period, TimeBucket $bucket): array
    {
        ['values' => $total, 'row_count' => $row_count] = MeasureSql::totals($scope, $query->measures);
        $series = BucketSeries::read($scope, $query, $period, $bucket);

        return ['series' => $series, 'total' => $total, 'other' => null, 'row_count' => $row_count, 'group_count' => count($series), 'truncated' => false];
    }

    /**
     * The groups of a column (spec 4.3): the series of the groups shown, the total over every row, the rest of
     * the groups when asked and some were left out, and how many groups there are.
     *
     * A grouped request with no limit is refused when it has more groups than the limit allows, before its
     * series is read. A null value of the column is a group of its own, with the key `null` and no label.
     *
     * @throws AggregateException
     * @throws AuthorizationException
     *
     * @return Figures
     */
    private function grouped(AggregateScope $scope, AggregateQuery $query, AggregateGroup $group): array
    {
        ['rows' => $rows, 'column' => $column, 'keyIsLabel' => $key_is_label] = $this->groupSource($scope, $group);

        $group_count = $this->groupCount($rows(), $column);

        if ($query->limit === null && $group_count > $this->limits->maxGroups) {
            throw new AggregateException(
                AggregateErrorCode::TooManyGroups,
                sprintf('Grouping by "%s" gives %d groups, more than %d: narrow the request with filters or set a limit.', $group->field, $group_count, $this->limits->maxGroups),
                'group_by.field',
            );
        }

        ['values' => $total, 'row_count' => $row_count] = MeasureSql::totals($scope, $query->measures);

        $groups = $this->groupRows($scope->model, $rows(), $column, $query);
        $keys = array_column($groups, 'key');
        $labels = $this->labels($scope, $group, $key_is_label, $keys);

        $series = [];

        foreach ($groups as $index => $shown) {
            $series[] = ['key' => $shown['key'], 'label' => $labels[$index], 'values' => $shown['values']];
        }

        $truncated = $group_count > count($series);
        $other = null;

        if ($query->other && $truncated) {
            $rest = $this->rest($rows(), $column, $keys);
            $rest_row = (array) $rest->select(MeasureSql::select($scope->model, $rest->getGrammar(), $query->measures))->first();

            $other = [...MeasureSql::figures($rest_row, $query->measures), 'groups' => $group_count - count($series)];
        }

        return ['series' => $series, 'total' => $total, 'other' => $other, 'row_count' => $row_count, 'group_count' => $group_count, 'truncated' => $truncated];
    }

    /**
     * The rows a group splits and the qualified column whose value keys a group.
     *
     * An own column splits the rows of the scope. A column of a single-hop to-one relation (`customer.country`)
     * splits them joined to the related table, as the facets do: a `BelongsTo` gives at most one related row, so
     * no row is counted twice; the rows of the scope enter through their keys, so its scopes cannot collide with
     * the related columns; and a related row the caller may not see takes its rows out of every group. Such a
     * value is its own label.
     *
     * @throws AggregateException INVALID_FIELD on `group_by.field` when the field is neither
     *
     * @return array{rows: Closure(): BaseQueryBuilder, column: string, keyIsLabel: bool}
     */
    private function groupSource(AggregateScope $scope, AggregateGroup $group): array
    {
        $model = $scope->model;

        if (! str_contains($group->field, '.')) {
            if (! ColumnKinds::exists($model, $group->field)) {
                throw self::unknownGroup($group->field);
            }

            return [
                'rows' => static fn (): BaseQueryBuilder => ($scope->baseQuery)()->toBase()->reorder(),
                'column' => $model->qualifyColumn($group->field),
                'keyIsLabel' => false,
            ];
        }

        $to_one = ($scope->resolveToOne)($group->field);

        if ($to_one === null || ! ColumnKinds::exists($to_one['related'], $to_one['column'])) {
            throw self::unknownGroup($group->field);
        }

        $owner_key = $to_one['relatedTable'] . '.' . $to_one['ownerKey'];
        $visible = ($scope->visibleRelatedKeys)($to_one['related'], $to_one['ownerKey']);

        return [
            'rows' => static fn (): BaseQueryBuilder => $model->getConnection()->query()
                ->from($model->getTable())
                ->join($to_one['relatedTable'], $model->qualifyColumn($to_one['foreignKey']), '=', $owner_key)
                ->whereIn($model->getQualifiedKeyName(), ($scope->filteredIds)())
                ->when($visible instanceof BaseQueryBuilder, static fn (BaseQueryBuilder $rows): BaseQueryBuilder => $rows->whereIn($owner_key, $visible)),
            'column' => $to_one['relatedTable'] . '.' . $to_one['column'],
            'keyIsLabel' => true,
        ];
    }

    /**
     * How many groups the rows give: the distinct values of the column, and one more when a row has no value,
     * which `count(distinct ...)` leaves out.
     */
    private function groupCount(BaseQueryBuilder $rows, string $column): int
    {
        $grammar = $rows->getGrammar();
        $key = $grammar->wrap($column);

        $row = (array) $rows->select([
            new Expression('count(distinct ' . $key . ') as ' . $grammar->wrap(self::DISTINCT_KEYS_ALIAS)),
            new Expression('count(*) - count(' . $key . ') as ' . $grammar->wrap(self::NULL_KEY_ROWS_ALIAS)),
        ])->first();

        $distinct_keys = (int) MeasureSql::figure($row[self::DISTINCT_KEYS_ALIAS] ?? 0);
        $null_key_rows = (int) MeasureSql::figure($row[self::NULL_KEY_ROWS_ALIAS] ?? 0);

        return $distinct_keys + ($null_key_rows > 0 ? 1 : 0);
    }

    /**
     * One query grouped by the column: the measures of each group, in the order of the sort (by the first
     * measure, the key breaking ties, or by the key), and no more groups than the limit.
     *
     * @throws AggregateException
     *
     * @return list<array{key: mixed, values: array<string, int|float|string|null>}>
     */
    private function groupRows(Model $model, BaseQueryBuilder $rows, string $column, AggregateQuery $query): array
    {
        $rows->select([$column . ' as ' . self::GROUP_KEY_ALIAS, ...MeasureSql::select($model, $rows->getGrammar(), $query->measures)])
            ->groupBy($column);

        if ($query->sortBy === 'key') {
            $rows->orderBy($column, $query->sortDir);
        } else {
            $rows->orderBy(MeasureSql::alias(0), $query->sortDir)->orderBy($column);
        }

        if ($query->limit !== null) {
            $rows->limit($query->limit);
        }

        $groups = [];

        foreach ($rows->get() as $row) {
            $row = (array) $row;
            $groups[] = ['key' => $row[self::GROUP_KEY_ALIAS] ?? null, 'values' => MeasureSql::figures($row, $query->measures)];
        }

        return $groups;
    }

    /**
     * The label of each group shown, in the order of the keys: the key itself for a relation column; for a group
     * labelled through `group_by.label`, the label read in one bounded read for the keys shown, under the
     * permission and ACL of the related entity, or the key when no label can be read; none for an own column.
     * A null key has no label.
     *
     * @param  list<mixed>  $keys
     * @return list<?string>
     */
    private function labels(AggregateScope $scope, AggregateGroup $group, bool $key_is_label, array $keys): array
    {
        $values = array_values(array_filter($keys, static fn (mixed $key): bool => $key !== null));

        $read = $group->label !== null && ! $key_is_label && $values !== []
            ? ($scope->resolveLabels)($group->field, $values, $group->label)
            : [];

        $labels = [];

        foreach ($keys as $key) {
            $read_label = is_int($key) || is_string($key) ? self::text($read[$key] ?? null) : null;

            $labels[] = match (true) {
                $key === null => null,
                $key_is_label => self::text($key),
                $group->label !== null => $read_label ?? self::text($key),
                default => null,
            };
        }

        return $labels;
    }

    /**
     * The rows of the groups that were not shown. A key list leaves the null group out by itself (`NOT IN` is not
     * true for a null value), so a null group that was not shown is added back, and one that was shown is left
     * out even when it is the only key.
     *
     * @param  list<mixed>  $keys
     */
    private function rest(BaseQueryBuilder $rows, string $column, array $keys): BaseQueryBuilder
    {
        $values = array_values(array_filter($keys, static fn (mixed $key): bool => $key !== null));

        if (count($values) < count($keys)) {
            return $rows->whereNotNull($column)->whereNotIn($column, $values);
        }

        return $rows->where(static fn (BaseQueryBuilder $rest): BaseQueryBuilder => $rest->whereNotIn($column, $values)->orWhereNull($column));
    }
}
