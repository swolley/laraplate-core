<?php

declare(strict_types=1);

use Modules\Core\Enums\AggregateErrorCode;
use Modules\Core\Enums\TimeBucket;
use Modules\Core\Exceptions\AggregateException;
use Modules\Core\Services\Crud\Aggregate\AggregateGroup;
use Modules\Core\Services\Crud\Aggregate\AggregateLimits;
use Modules\Core\Services\Crud\Aggregate\AggregateMeasure;
use Modules\Core\Services\Crud\Aggregate\AggregateQuery;

beforeEach(function (): void {
    $this->limits = new AggregateLimits(
        maxMeasures: 3,
        maxGroups: 50,
        maxPoints: 400,
        maxPeriodDays: 1096,
        maxObjectsPerMessage: 6,
        ratePerMinute: 60,
    );
});

it('accepts a count with no field and a sum with a field', function (): void {
    $query = AggregateQuery::fromArray([
        'measures' => [['fn' => 'count'], ['fn' => 'sum', 'field' => 'total']],
    ], $this->limits);

    expect($query->measures)->toHaveCount(2)
        ->and($query->measures[0])->toEqual(new AggregateMeasure('count', null))
        ->and($query->measures[1])->toEqual(new AggregateMeasure('sum', 'total'))
        ->and($query->group)->toBeNull()
        ->and($query->period)->toBeNull()
        ->and($query->limit)->toBeNull()
        ->and($query->other)->toBeFalse()
        ->and($query->compare)->toBeFalse();
});

it('names measures', function (AggregateMeasure $measure, string $name): void {
    expect($measure->name())->toBe($name);
})->with([
    'a count with no field' => [new AggregateMeasure('count', null), 'count'],
    'a sum' => [new AggregateMeasure('sum', 'total'), 'sum_total'],
    'a count over a field' => [new AggregateMeasure('count', 'status'), 'count_status'],
]);

it('reads the group by a column and by a bucket', function (): void {
    $column = AggregateQuery::fromArray([
        'measures' => [['fn' => 'count']],
        'group_by' => ['field' => 'customer_id', 'label' => 'customer.name'],
    ], $this->limits);

    $bucketed = AggregateQuery::fromArray([
        'measures' => [['fn' => 'count']],
        'group_by' => ['field' => 'ordered_at', 'bucket' => 'month'],
        'period' => ['field' => 'ordered_at', 'preset' => 'this_year'],
    ], $this->limits);

    expect($column->group)->toEqual(new AggregateGroup('customer_id', null, 'customer.name'))
        ->and($bucketed->group)->toEqual(new AggregateGroup('ordered_at', TimeBucket::Month, null));
});

it('refuses a request it cannot run', function (array $input, AggregateErrorCode $code, string $field): void {
    $build = fn (): AggregateQuery => AggregateQuery::fromArray($input, $this->limits);

    expect($build)->toThrow(static fn (AggregateException $exception) => expect($exception->errorCode)->toBe($code)
        ->and($exception->field)->toBe($field));
})->with([
    'a function outside the five' => [
        ['measures' => [['fn' => 'median', 'field' => 'total']]],
        AggregateErrorCode::UnsupportedFunction, 'measures.0.fn',
    ],
    'an unknown function after a good measure' => [
        ['measures' => [['fn' => 'count'], ['fn' => 'stddev', 'field' => 'total']]],
        AggregateErrorCode::UnsupportedFunction, 'measures.1.fn',
    ],
    'a function in the wrong case' => [
        ['measures' => [['fn' => 'SUM', 'field' => 'total']]],
        AggregateErrorCode::UnsupportedFunction, 'measures.0.fn',
    ],
    'a function that is not a string' => [
        ['measures' => [['fn' => ['sum'], 'field' => 'total']]],
        AggregateErrorCode::InvalidField, 'measures.0.fn',
    ],
    'a measure with no function' => [
        ['measures' => [['field' => 'total']]],
        AggregateErrorCode::InvalidField, 'measures.0.fn',
    ],
    'a sum with no field' => [
        ['measures' => [['fn' => 'sum']]],
        AggregateErrorCode::InvalidField, 'measures.0.field',
    ],
    'an avg with an empty field' => [
        ['measures' => [['fn' => 'count'], ['fn' => 'avg', 'field' => '']]],
        AggregateErrorCode::InvalidField, 'measures.1.field',
    ],
    'a field that is not a string' => [
        ['measures' => [['fn' => 'max', 'field' => ['total']]]],
        AggregateErrorCode::InvalidField, 'measures.0.field',
    ],
    'a measure that is not an object' => [
        ['measures' => ['count']],
        AggregateErrorCode::InvalidField, 'measures.0',
    ],
    'more measures than the limit' => [
        ['measures' => [['fn' => 'count'], ['fn' => 'sum', 'field' => 'a'], ['fn' => 'sum', 'field' => 'b'], ['fn' => 'sum', 'field' => 'c']]],
        AggregateErrorCode::InvalidField, 'measures',
    ],
    'no measures' => [
        ['measures' => []],
        AggregateErrorCode::InvalidField, 'measures',
    ],
    'measures missing' => [
        [],
        AggregateErrorCode::InvalidField, 'measures',
    ],
    'measures as one object instead of a list' => [
        ['measures' => ['fn' => 'count']],
        AggregateErrorCode::InvalidField, 'measures',
    ],
    'a bucket outside the five' => [
        [
            'measures' => [['fn' => 'count']],
            'group_by' => ['field' => 'ordered_at', 'bucket' => 'fortnight'],
            'period' => ['field' => 'ordered_at', 'preset' => 'this_year'],
        ],
        AggregateErrorCode::InvalidField, 'group_by.bucket',
    ],
    'a bucket that is not a string' => [
        [
            'measures' => [['fn' => 'count']],
            'group_by' => ['field' => 'ordered_at', 'bucket' => 7],
            'period' => ['field' => 'ordered_at', 'preset' => 'this_year'],
        ],
        AggregateErrorCode::InvalidField, 'group_by.bucket',
    ],
    'a bucketed group with no period' => [
        ['measures' => [['fn' => 'count']], 'group_by' => ['field' => 'ordered_at', 'bucket' => 'month']],
        AggregateErrorCode::InvalidField, 'period',
    ],
    'a group with no field' => [
        ['measures' => [['fn' => 'count']], 'group_by' => ['bucket' => 'month']],
        AggregateErrorCode::InvalidField, 'group_by.field',
    ],
    'a group given as a list of columns, the list vocabulary' => [
        ['measures' => [['fn' => 'count']], 'group_by' => ['status']],
        AggregateErrorCode::InvalidField, 'group_by',
    ],
    'a label that is not a string' => [
        ['measures' => [['fn' => 'count']], 'group_by' => ['field' => 'customer_id', 'label' => ['customer.name']]],
        AggregateErrorCode::InvalidField, 'group_by.label',
    ],
    'compare with no period' => [
        ['measures' => [['fn' => 'count']], 'compare' => 'previous_period'],
        AggregateErrorCode::InvalidField, 'compare',
    ],
    'a compare other than the previous period' => [
        [
            'measures' => [['fn' => 'count']],
            'period' => ['field' => 'ordered_at', 'preset' => 'this_year'],
            'compare' => 'next_period',
        ],
        AggregateErrorCode::InvalidField, 'compare',
    ],
    'other with no limit' => [
        ['measures' => [['fn' => 'count']], 'group_by' => ['field' => 'status'], 'other' => true],
        AggregateErrorCode::InvalidField, 'other',
    ],
    'other that is not a boolean' => [
        ['measures' => [['fn' => 'count']], 'group_by' => ['field' => 'status'], 'limit' => 5, 'other' => 'yes'],
        AggregateErrorCode::InvalidField, 'other',
    ],
    'a limit above max_groups' => [
        ['measures' => [['fn' => 'count']], 'group_by' => ['field' => 'status'], 'limit' => 51],
        AggregateErrorCode::TooManyGroups, 'limit',
    ],
    'a limit of zero' => [
        ['measures' => [['fn' => 'count']], 'group_by' => ['field' => 'status'], 'limit' => 0],
        AggregateErrorCode::InvalidField, 'limit',
    ],
    'a limit that is not a number' => [
        ['measures' => [['fn' => 'count']], 'group_by' => ['field' => 'status'], 'limit' => 'many'],
        AggregateErrorCode::InvalidField, 'limit',
    ],
    'a limit with a fraction' => [
        ['measures' => [['fn' => 'count']], 'group_by' => ['field' => 'status'], 'limit' => 2.5],
        AggregateErrorCode::InvalidField, 'limit',
    ],
    'a period with two of last, preset and from' => [
        [
            'measures' => [['fn' => 'count']],
            'period' => ['field' => 'ordered_at', 'last' => ['n' => 3, 'unit' => 'month'], 'preset' => 'this_month'],
        ],
        AggregateErrorCode::InvalidField, 'period',
    ],
    'a period with a preset and a range' => [
        [
            'measures' => [['fn' => 'count']],
            'period' => ['field' => 'ordered_at', 'preset' => 'this_month', 'from' => '2026-01-01', 'to' => '2026-01-31'],
        ],
        AggregateErrorCode::InvalidField, 'period',
    ],
    'a period with none of them' => [
        ['measures' => [['fn' => 'count']], 'period' => ['field' => 'ordered_at']],
        AggregateErrorCode::InvalidField, 'period',
    ],
    'a period with no field' => [
        ['measures' => [['fn' => 'count']], 'period' => ['preset' => 'this_month']],
        AggregateErrorCode::InvalidField, 'period',
    ],
    'a period that is not an object' => [
        ['measures' => [['fn' => 'count']], 'period' => 'this_month'],
        AggregateErrorCode::InvalidField, 'period',
    ],
    'an unknown preset' => [
        ['measures' => [['fn' => 'count']], 'period' => ['field' => 'ordered_at', 'preset' => 'next_week']],
        AggregateErrorCode::InvalidField, 'period',
    ],
    'a count of zero in last' => [
        ['measures' => [['fn' => 'count']], 'period' => ['field' => 'ordered_at', 'last' => ['n' => '0', 'unit' => 'day']]],
        AggregateErrorCode::InvalidField, 'period',
    ],
    'a count that is not a whole number in last' => [
        ['measures' => [['fn' => 'count']], 'period' => ['field' => 'ordered_at', 'last' => ['n' => '2.5', 'unit' => 'day']]],
        AggregateErrorCode::InvalidField, 'period',
    ],
    'a unit outside the five in last' => [
        ['measures' => [['fn' => 'count']], 'period' => ['field' => 'ordered_at', 'last' => ['n' => 2, 'unit' => 'months']]],
        AggregateErrorCode::InvalidField, 'period',
    ],
    'a range that ends before it starts' => [
        ['measures' => [['fn' => 'count']], 'period' => ['field' => 'ordered_at', 'from' => '2026-02-01', 'to' => '2026-01-01']],
        AggregateErrorCode::InvalidField, 'period',
    ],
    'a sort by anything else than measure or key' => [
        ['measures' => [['fn' => 'count']], 'group_by' => ['field' => 'status'], 'sort' => ['by' => 'label', 'dir' => 'asc']],
        AggregateErrorCode::InvalidField, 'sort.by',
    ],
    'a sort direction other than asc or desc' => [
        ['measures' => [['fn' => 'count']], 'group_by' => ['field' => 'status'], 'sort' => ['by' => 'key', 'dir' => 'up']],
        AggregateErrorCode::InvalidField, 'sort.dir',
    ],
    'a sort direction in the wrong case' => [
        ['measures' => [['fn' => 'count']], 'group_by' => ['field' => 'status'], 'sort' => ['by' => 'key', 'dir' => 'ASC']],
        AggregateErrorCode::InvalidField, 'sort.dir',
    ],
    'a sort given as a list, the list vocabulary' => [
        ['measures' => [['fn' => 'count']], 'group_by' => ['field' => 'status'], 'sort' => ['measure']],
        AggregateErrorCode::InvalidField, 'sort',
    ],
]);

it('applies the limits it is given', function (): void {
    $limits = new AggregateLimits(maxMeasures: 1, maxGroups: 5, maxPoints: 400, maxPeriodDays: 1096, maxObjectsPerMessage: 6, ratePerMinute: 60);

    $measures = fn (): AggregateQuery => AggregateQuery::fromArray(['measures' => [['fn' => 'count'], ['fn' => 'count']]], $limits);
    $groups = fn (): AggregateQuery => AggregateQuery::fromArray([
        'measures' => [['fn' => 'count']],
        'group_by' => ['field' => 'status'],
        'limit' => 6,
    ], $limits);
    $allowed = AggregateQuery::fromArray([
        'measures' => [['fn' => 'count']],
        'group_by' => ['field' => 'status'],
        'limit' => 5,
    ], $limits);

    expect($measures)->toThrow(AggregateException::class)
        ->and($groups)->toThrow(AggregateException::class)
        ->and($allowed->limit)->toBe(5);
});

it('defaults the sort to the first measure descending for a column group', function (): void {
    $query = AggregateQuery::fromArray([
        'measures' => [['fn' => 'count']],
        'group_by' => ['field' => 'status'],
    ], $this->limits);

    expect($query->sortBy)->toBe('measure')
        ->and($query->sortDir)->toBe('desc');
});

it('defaults the sort to the key ascending for a bucketed group', function (): void {
    $query = AggregateQuery::fromArray([
        'measures' => [['fn' => 'count']],
        'group_by' => ['field' => 'ordered_at', 'bucket' => 'week'],
        'period' => ['field' => 'ordered_at', 'last' => ['n' => 8, 'unit' => 'week']],
    ], $this->limits);

    expect($query->sortBy)->toBe('key')
        ->and($query->sortDir)->toBe('asc');
});

it('defaults the sort of a request with no group', function (): void {
    $query = AggregateQuery::fromArray(['measures' => [['fn' => 'count']]], $this->limits);

    expect($query->sortBy)->toBe('measure')
        ->and($query->sortDir)->toBe('desc');
});

it('reads a sort the request gives and fills what it leaves out', function (array $input, bool $bucketed, string $by, string $dir): void {
    $request = [
        'measures' => [['fn' => 'count']],
        'group_by' => $bucketed ? ['field' => 'ordered_at', 'bucket' => 'month'] : ['field' => 'status'],
        'sort' => $input,
    ];

    if ($bucketed) {
        $request['period'] = ['field' => 'ordered_at', 'preset' => 'this_year'];
    }

    $query = AggregateQuery::fromArray($request, $this->limits);

    expect($query->sortBy)->toBe($by)
        ->and($query->sortDir)->toBe($dir);
})->with([
    'both' => [['by' => 'key', 'dir' => 'desc'], false, 'key', 'desc'],
    'a measure ascending' => [['by' => 'measure', 'dir' => 'asc'], false, 'measure', 'asc'],
    'a key with no direction is ascending' => [['by' => 'key'], false, 'key', 'asc'],
    'a measure with no direction is descending' => [['by' => 'measure'], true, 'measure', 'desc'],
    'a direction alone keeps the default key of a bucket' => [['dir' => 'desc'], true, 'key', 'desc'],
    'a direction alone keeps the default measure of a column' => [['dir' => 'asc'], false, 'measure', 'asc'],
    'an empty sort is the default' => [[], true, 'key', 'asc'],
]);

it('stores a period with its count as an integer', function (mixed $count): void {
    $query = AggregateQuery::fromArray([
        'measures' => [['fn' => 'count']],
        'period' => ['field' => 'ordered_at', 'last' => ['n' => $count, 'unit' => 'month']],
    ], $this->limits);

    expect($query->period)->toBe(['field' => 'ordered_at', 'last' => ['n' => 12, 'unit' => 'month']])
        ->and($query->toArray()['period']['last']['n'])->toBe(12);
})->with([
    'an integer' => [12],
    'a numeric string, as a query string gives it' => ['12'],
    'a whole float, as a JSON number can decode' => [12.0],
    'a numeric string with leading zeros' => ['012'],
]);

it('stores the period as the validated spec and nothing else', function (): void {
    $query = AggregateQuery::fromArray([
        'measures' => [['fn' => 'count']],
        'period' => [
            'field' => 'ordered_at',
            'preset' => 'this_quarter',
            'last' => null,
            'from' => null,
            'noise' => 'ignored',
        ],
    ], $this->limits);

    expect($query->period)->toBe(['field' => 'ordered_at', 'preset' => 'this_quarter']);
});

it('stores every form of the period', function (array $period): void {
    $query = AggregateQuery::fromArray(['measures' => [['fn' => 'count']], 'period' => $period], $this->limits);

    expect($query->period)->toBe($period);
})->with([
    'last' => [['field' => 'ordered_at', 'last' => ['n' => 7, 'unit' => 'day']]],
    'preset' => [['field' => 'ordered_at', 'preset' => 'last_month']],
    'range' => [['field' => 'ordered_at', 'from' => '2026-07-01', 'to' => '2026-09-30']],
]);

it('reads the limit, other and compare in the forms a client sends', function (mixed $limit, mixed $other, mixed $compare, ?int $expectedLimit, bool $expectedOther, bool $expectedCompare): void {
    $query = AggregateQuery::fromArray([
        'measures' => [['fn' => 'count']],
        'group_by' => ['field' => 'status'],
        'period' => ['field' => 'ordered_at', 'preset' => 'this_year'],
        'limit' => $limit,
        'other' => $other,
        'compare' => $compare,
    ], $this->limits);

    expect($query->limit)->toBe($expectedLimit)
        ->and($query->other)->toBe($expectedOther)
        ->and($query->compare)->toBe($expectedCompare);
})->with([
    'all absent as null' => [null, null, null, null, false, false],
    'all off' => [null, false, false, null, false, false],
    'a numeric string limit' => ['5', true, 'previous_period', 5, true, true],
    'a whole float limit' => [5.0, true, true, 5, true, true],
    'an integer limit' => [50, false, 'previous_period', 50, false, true],
]);

it('gives the normalized request without the kind and the resource', function (): void {
    $query = AggregateQuery::fromArray([
        'kind' => 'aggregate',
        'resource' => 'erp/orders',
        'measures' => [['fn' => 'count'], ['fn' => 'sum', 'field' => 'total']],
        'group_by' => ['field' => 'customer_id', 'label' => 'customer.name'],
        'period' => ['field' => 'ordered_at', 'last' => ['n' => '6', 'unit' => 'month']],
        'limit' => '5',
        'other' => true,
        'compare' => 'previous_period',
    ], $this->limits);

    expect($query->toArray())->toBe([
        'measures' => [['fn' => 'count'], ['fn' => 'sum', 'field' => 'total']],
        'group_by' => ['field' => 'customer_id', 'label' => 'customer.name'],
        'period' => ['field' => 'ordered_at', 'last' => ['n' => 6, 'unit' => 'month']],
        'sort' => ['by' => 'measure', 'dir' => 'desc'],
        'limit' => 5,
        'other' => true,
        'compare' => 'previous_period',
    ]);
});

it('leaves out what the request did not ask for', function (): void {
    $query = AggregateQuery::fromArray(['measures' => [['fn' => 'avg', 'field' => 'total']]], $this->limits);

    expect($query->toArray())->toBe([
        'measures' => [['fn' => 'avg', 'field' => 'total']],
        'sort' => ['by' => 'measure', 'dir' => 'desc'],
    ]);
});

it('gives a normalized array that builds the same query again', function (): void {
    $query = AggregateQuery::fromArray([
        'measures' => [['fn' => 'count'], ['fn' => 'max', 'field' => 'total']],
        'group_by' => ['field' => 'ordered_at', 'bucket' => 'quarter'],
        'period' => ['field' => 'ordered_at', 'from' => '2025-01-01', 'to' => '2026-09-30'],
        'sort' => ['by' => 'measure', 'dir' => 'asc'],
        'compare' => true,
    ], $this->limits);

    $again = AggregateQuery::fromArray($query->toArray(), $this->limits);

    expect($again)->toEqual($query)
        ->and($again->toArray())->toBe($query->toArray());
});

it('gives the same canonical form to the same request in another key order', function (): void {
    $first = AggregateQuery::fromArray([
        'measures' => [['fn' => 'sum', 'field' => 'total']],
        'group_by' => ['field' => 'customer_id', 'label' => 'customer.name'],
        'period' => ['field' => 'ordered_at', 'last' => ['n' => 3, 'unit' => 'month']],
        'limit' => 5,
    ], $this->limits);

    $second = AggregateQuery::fromArray([
        'limit' => '5',
        'period' => ['last' => ['unit' => 'month', 'n' => '3'], 'field' => 'ordered_at'],
        'group_by' => ['label' => 'customer.name', 'field' => 'customer_id'],
        'measures' => [['field' => 'total', 'fn' => 'sum']],
        'sort' => ['dir' => 'desc', 'by' => 'measure'],
        'other' => false,
    ], $this->limits);

    expect($second->canonical())->toBe($first->canonical())
        ->and($first->canonical())->toBeJson();
});

it('sorts the keys of the canonical form but not the measures', function (): void {
    $query = AggregateQuery::fromArray([
        'measures' => [['fn' => 'sum', 'field' => 'total'], ['fn' => 'count']],
        'compare' => 'previous_period',
        'period' => ['field' => 'ordered_at', 'preset' => 'this_month'],
    ], $this->limits);

    expect($query->canonical())->toBe(
        '{"compare":"previous_period","measures":[{"field":"total","fn":"sum"},{"fn":"count"}],"period":{"field":"ordered_at","preset":"this_month"},"sort":{"by":"measure","dir":"desc"}}',
    );
});

it('gives another canonical form to another request', function (array $other): void {
    $base = AggregateQuery::fromArray(['measures' => [['fn' => 'sum', 'field' => 'total']]], $this->limits);
    $different = AggregateQuery::fromArray($other, $this->limits);

    expect($different->canonical())->not->toBe($base->canonical());
})->with([
    'another measure field' => [['measures' => [['fn' => 'sum', 'field' => 'quantity']]]],
    'another function' => [['measures' => [['fn' => 'avg', 'field' => 'total']]]],
    'an added measure' => [['measures' => [['fn' => 'count'], ['fn' => 'sum', 'field' => 'total']]]],
    'a group' => [['measures' => [['fn' => 'sum', 'field' => 'total']], 'group_by' => ['field' => 'status']]],
    'another sort' => [['measures' => [['fn' => 'sum', 'field' => 'total']], 'sort' => ['by' => 'measure', 'dir' => 'asc']]],
]);
