<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\Aggregate;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar;
use LogicException;
use Modules\Core\Enums\AggregateErrorCode;
use Modules\Core\Exceptions\AggregateException;
use Modules\Core\Support\ColumnKinds;
use UnexpectedValueException;

/**
 * Runs an {@see AggregateQuery} over the rows of an {@see AggregateScope} and returns the {@see AggregateResult} of
 * the contract.
 *
 * Every figure is computed by SQL over the base query of the scope, so the row-level ACL and the list filters
 * bound it. No text of the request reaches SQL: a field is used only once it is a column of the model
 * ({@see ColumnKinds::exists()}), and then qualified by its table and wrapped by the grammar of the connection; a
 * function is one of a closed list; a figure is selected under a positional alias (`m0`, `m1`, ...) and named
 * after its measure in PHP.
 *
 * Whether the caller may read a field and whether a measure suits its column is checked by the CRUD service before
 * the engine runs. The engine answers an ungrouped request with no period: grouping and periods are paths of their
 * own, and a request that needs one is refused with a {@see LogicException} until it exists.
 */
final class AggregateEngine
{
    private const string ROW_COUNT_ALIAS = 'row_count';

    public function __construct(private readonly AggregateLimits $limits) {}

    /**
     * The limits are applied here too, because an {@see AggregateQuery} can be built without
     * {@see AggregateQuery::fromArray()}, which applies them to a request.
     *
     * @throws AggregateException when the query holds more measures than the limit, or a measure is not a function of the closed list or names a field that is not a column of the model
     */
    public function run(AggregateScope $scope, AggregateQuery $query, CarbonImmutable $now): AggregateResult
    {
        if ($this->limits->maxMeasures < count($query->measures)) {
            throw new AggregateException(
                AggregateErrorCode::InvalidField,
                sprintf('An aggregate holds at most %d measures.', $this->limits->maxMeasures),
                'measures',
            );
        }

        if ($query->group instanceof AggregateGroup) {
            throw new LogicException('A grouped aggregate is not computed yet: the engine computes the totals only.');
        }

        if ($query->period !== null) {
            throw new LogicException('An aggregate over a period is not computed yet: the engine computes the totals only.');
        }

        ['values' => $total, 'row_count' => $row_count] = $this->totals($scope, $query->measures);

        return new AggregateResult(
            series: [],
            total: $total,
            other: null,
            compare: null,
            meta: [
                'row_count' => $row_count,
                'group_count' => 0,
                'truncated' => false,
                'generated_at' => $now->toIso8601ZuluString(),
            ],
        );
    }

    private static function alias(int $index): string
    {
        return 'm' . $index;
    }

    /**
     * A figure as JSON carries it (ruling R8): a number stays a number, and a numeric string, as which a driver
     * may return a decimal or a count, becomes the number it spells. Any other text is the minimum or the maximum
     * of a date-time column, and stays as the database holds it.
     */
    private static function figure(mixed $value): int|float|string|null
    {
        if ($value === null || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return is_numeric($value) ? 0 + $value : $value;
        }

        throw new UnexpectedValueException(sprintf('An aggregate function returned %s, which is neither a number nor a text.', get_debug_type($value)));
    }

    /**
     * One query over every row of the scope: each measure, and the number of rows.
     *
     * The ordering of the base query is dropped: the default ordering of a model means nothing to an aggregate,
     * and an ORDER BY on a plain column next to an aggregate function is refused by MySQL (only_full_group_by)
     * and by PostgreSQL.
     *
     * @param  list<AggregateMeasure>  $measures
     *
     * @throws AggregateException
     *
     * @return array{values: array<string, int|float|string|null>, row_count: int}
     */
    private function totals(AggregateScope $scope, array $measures): array
    {
        $query = ($scope->baseQuery)()->toBase()->reorder();
        $grammar = $query->getGrammar();

        $select = [];

        foreach ($measures as $index => $measure) {
            $select[] = new Expression($this->measureExpression($scope->model, $grammar, $measure, $index) . ' as ' . $grammar->wrap(self::alias($index)));
        }

        $select[] = new Expression('count(*) as ' . $grammar->wrap(self::ROW_COUNT_ALIAS));

        $row = (array) $query->select($select)->first();

        $values = [];

        foreach ($measures as $index => $measure) {
            $values[$measure->name()] = self::figure($row[self::alias($index)] ?? null);
        }

        return ['values' => $values, 'row_count' => (int) self::figure($row[self::ROW_COUNT_ALIAS] ?? 0)];
    }

    /**
     * The SQL of one measure: `count(*)`, or a function of the closed list over the qualified, wrapped column.
     *
     * @throws AggregateException
     */
    private function measureExpression(Model $model, Grammar $grammar, AggregateMeasure $measure, int $index): string
    {
        $function = match ($measure->fn) {
            'count' => 'count',
            'sum' => 'sum',
            'avg' => 'avg',
            'min' => 'min',
            'max' => 'max',
            default => throw new AggregateException(
                AggregateErrorCode::UnsupportedFunction,
                'The function of a measure is one of count, sum, avg, min, max.',
                'measures.' . $index . '.fn',
            ),
        };

        if ($measure->field === null) {
            throw_unless($function === 'count', new AggregateException(
                AggregateErrorCode::InvalidField,
                sprintf('The function "%s" needs a field.', $function),
                'measures.' . $index . '.field',
            ));

            return 'count(*)';
        }

        throw_unless(ColumnKinds::exists($model, $measure->field), new AggregateException(
            AggregateErrorCode::InvalidField,
            sprintf('"%s" is not a column of the entity.', $measure->field),
            'measures.' . $index . '.field',
        ));

        return $function . '(' . $grammar->wrap($model->qualifyColumn($measure->field)) . ')';
    }
}
