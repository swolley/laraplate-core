<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Enums\AggregateErrorCode;
use Modules\Core\Enums\TimeBucket;
use Modules\Core\Exceptions\AggregateException;
use Modules\Core\Http\Requests\AggregateRequest;
use Modules\Core\Services\Crud\Aggregate\AggregateGroup;

/**
 * An aggregate request for `core/settings` as the route will hand it over, validated the way the framework
 * validates a form request: the route itself is not mounted yet, so this is the whole request path short of it.
 *
 * @param  array<string, mixed>  $input
 */
function aggreq_validated(array $input, string $method = 'POST'): AggregateRequest
{
    $request = AggregateRequest::create('/app/crud/aggregate/core/settings', $method, $input);

    $route = new Route($method, '/app/crud/aggregate/{module}/{entity}', fn (): null => null);
    $route->bind($request);
    $route->setParameter('module', 'core');
    $route->setParameter('entity', 'settings');
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setRouteResolver(fn (): Route => $route);
    $request->validateResolved();

    return $request;
}

it('builds the aggregate query from a JSON body', function (): void {
    $query = aggreq_validated([
        'measures' => [['fn' => 'count'], ['fn' => 'sum', 'field' => 'id']],
        'group_by' => ['field' => 'group_name'],
        'period' => ['field' => 'created_at', 'last' => ['n' => 6, 'unit' => 'month']],
        'sort' => ['by' => 'measure', 'dir' => 'asc'],
        'limit' => 10,
        'other' => true,
        'compare' => 'previous_period',
    ])->aggregateQuery();

    expect($query->measures)->toHaveCount(2)
        ->and($query->group)->toEqual(new AggregateGroup('group_name'))
        ->and($query->period)->toBe(['field' => 'created_at', 'last' => ['n' => 6, 'unit' => 'month']])
        ->and($query->sortBy)->toBe('measure')
        ->and($query->sortDir)->toBe('asc')
        ->and($query->limit)->toBe(10)
        ->and($query->other)->toBeTrue()
        ->and($query->compare)->toBeTrue();
});

it('builds a bucketed query from a JSON body', function (): void {
    $query = aggreq_validated([
        'measures' => [['fn' => 'count']],
        'group_by' => ['field' => 'created_at', 'bucket' => 'month'],
        'period' => ['field' => 'created_at', 'last' => ['n' => 6, 'unit' => 'month']],
    ])->aggregateQuery();

    expect($query->group)->toEqual(new AggregateGroup('created_at', TimeBucket::Month, null))
        ->and($query->sortBy)->toBe('key')
        ->and($query->sortDir)->toBe('asc');
});

it('decodes the JSON strings a GET caller sends', function (): void {
    $query = aggreq_validated([
        'measures' => '[{"fn":"count"}]',
        'group_by' => '{"field":"group_name"}',
        'period' => '{"field":"created_at","last":{"n":"3","unit":"month"}}',
        'sort' => '{"by":"key","dir":"desc"}',
        'limit' => '5',
        'other' => 'true',
        'compare' => 'previous_period',
    ], 'GET')->aggregateQuery();

    expect($query->measures[0]->fn)->toBe('count')
        ->and($query->group?->field)->toBe('group_name')
        ->and($query->period)->toBe(['field' => 'created_at', 'last' => ['n' => 3, 'unit' => 'month']])
        ->and($query->sortBy)->toBe('key')
        ->and($query->sortDir)->toBe('desc')
        ->and($query->limit)->toBe(5)
        ->and($query->other)->toBeTrue()
        ->and($query->compare)->toBeTrue();
});

it('reads other and compare written as false by a GET caller', function (): void {
    $query = aggreq_validated([
        'measures' => '[{"fn":"count"}]',
        'other' => 'false',
        'compare' => 'false',
    ], 'GET')->aggregateQuery();

    expect($query->other)->toBeFalse()
        ->and($query->compare)->toBeFalse();
});

it('does not decode what the list request has already decoded', function (): void {
    expect((new ReflectionClassConstant(AggregateRequest::class, 'JSON_KEYS'))->getValue())->toBe(['measures', 'period']);
});

it('keeps the list vocabulary next to the aggregate keys', function (): void {
    $request = aggreq_validated([
        'measures' => [['fn' => 'count']],
        'filters' => [['property' => 'name', 'operator' => '=', 'value' => 'a']],
    ]);

    expect($request->parsed()->filters)->toBeInstanceOf(FiltersGroup::class);
});

it('keeps the aggregate keys out of the list data', function (): void {
    $parsed = aggreq_validated([
        'measures' => [['fn' => 'count']],
        'group_by' => ['field' => 'group_name', 'label' => 'name'],
        'sort' => ['by' => 'key', 'dir' => 'desc'],
        'limit' => 7,
        'period' => ['field' => 'created_at', 'preset' => 'this_year'],
        'other' => true,
        'compare' => 'previous_period',
    ])->parsed();

    expect($parsed->mainEntity)->toBe('settings')
        ->and($parsed->group_by)->toBe([])
        ->and($parsed->sort)->toBe([])
        ->and($parsed->limit)->not->toBe(7)
        ->and($parsed->limit)->toBe($parsed->pagination);
});

it('drops the list rules that mean something else here', function (): void {
    $rules = AggregateRequest::create('/app/crud/aggregate/core/settings', 'POST')->rules();

    expect($rules)->not->toHaveKeys(['group_by.*', 'sort.*.property', 'sort.*.direction'])
        ->and($rules)->toHaveKeys(['measures', 'measures.*.fn', 'measures.*.field', 'group_by.field', 'group_by.bucket', 'group_by.label', 'period', 'sort.by', 'sort.dir', 'limit', 'other', 'compare'])
        ->and($rules)->toHaveKeys(['filters', 'columns.*', 'relations', 'pagination', 'check_ids']);
});

it('bounds the measures by the configured limit and requires a list', function (): void {
    config(['core.aggregate.max_measures' => 2]);

    $rules = AggregateRequest::create('/app/crud/aggregate/core/settings', 'POST')->rules();

    expect($rules['measures'])->toContain('required', 'array', 'list', 'min:1', 'max:2');
});

it('replaces the list rule of limit', function (): void {
    $rules = AggregateRequest::create('/app/crud/aggregate/core/settings', 'POST')->rules();

    expect($rules['limit'])->not->toContain('exclude_if:count,true')
        ->and($rules['limit'])->toContain('integer', 'min:1');
});

it('refuses a shape error as INVALID_FIELD on the first failing key', function (array $input, string $field): void {
    $validate = static fn (): AggregateRequest => aggreq_validated($input);

    expect($validate)->toThrow(static fn (AggregateException $exception) => expect($exception->errorCode)->toBe(AggregateErrorCode::InvalidField)
        ->and($exception->field)->toBe($field)
        ->and($exception->getMessage())->not->toBe(''));
})->with([
    'no measures' => [[], 'measures'],
    'an empty list of measures' => [['measures' => []], 'measures'],
    'measures that are not JSON' => [['measures' => 'count'], 'measures'],
    'a measure that is not an object' => [['measures' => ['count']], 'measures.0'],
    'a measure with no function' => [['measures' => [['field' => 'id']]], 'measures.0.fn'],
    'a function that is not a string' => [['measures' => [['fn' => ['count']]]], 'measures.0.fn'],
    'a field that is not a string' => [['measures' => [['fn' => 'sum', 'field' => ['id']]]], 'measures.0.field'],
    'a group with no field' => [['measures' => [['fn' => 'count']], 'group_by' => ['bucket' => 'month']], 'group_by.field'],
    'a group given as a column name' => [['measures' => [['fn' => 'count']], 'group_by' => 'group_name'], 'group_by'],
    'a period that is not an object' => [['measures' => [['fn' => 'count']], 'period' => 'this_month'], 'period'],
    'a sort that is not an object' => [['measures' => [['fn' => 'count']], 'sort' => 5], 'sort'],
    'a limit of zero' => [['measures' => [['fn' => 'count']], 'limit' => 0], 'limit'],
    'a limit that is not a number' => [['measures' => [['fn' => 'count']], 'limit' => 'many'], 'limit'],
    'other that is not a boolean' => [['measures' => [['fn' => 'count']], 'other' => 'maybe'], 'other'],
]);

it('answers with the first message of the validator', function (): void {
    $validate = static fn (): AggregateRequest => aggreq_validated([]);

    expect($validate)->toThrow(AggregateException::class, 'The measures field is required.');
});

it('names the first failing key when several fail', function (): void {
    $validate = static fn (): AggregateRequest => aggreq_validated([
        'measures' => [['fn' => 'count']],
        'group_by' => ['bucket' => 'month'],
        'limit' => 0,
    ]);

    expect($validate)->toThrow(static fn (AggregateException $exception) => expect($exception->field)->toBe('group_by.field'));
});

it('leaves what the query itself refuses to the query', function (): void {
    $request = aggreq_validated(['measures' => [['fn' => 'median', 'field' => 'id']]]);

    expect($request->aggregateQuery(...))->toThrow(static fn (AggregateException $exception) => expect($exception->errorCode)->toBe(AggregateErrorCode::UnsupportedFunction)
        ->and($exception->field)->toBe('measures.0.fn'));
});

it('reads the limits from the config', function (): void {
    config(['core.aggregate.max_groups' => 5]);

    $request = aggreq_validated(['measures' => [['fn' => 'count']], 'group_by' => ['field' => 'group_name'], 'limit' => 6]);

    expect($request->aggregateQuery(...))->toThrow(static fn (AggregateException $exception) => expect($exception->errorCode)->toBe(AggregateErrorCode::TooManyGroups)
        ->and($exception->field)->toBe('limit'));
});

it('refuses measures given as an object without echoing its keys', function (mixed $measures): void {
    $validate = static fn (): AggregateRequest => aggreq_validated(['measures' => $measures]);

    expect($validate)->toThrow(static fn (AggregateException $exception) => expect($exception->errorCode)->toBe(AggregateErrorCode::InvalidField)
        ->and($exception->field)->toBe('measures')
        ->and($exception->getMessage())->not->toContain('leaky_key')
        ->and($exception->getMessage())->toContain('list'));
})->with([
    'an object of objects' => [['leaky_key' => ['fn' => 'count']]],
    'an object of values' => [['leaky_key' => 'count']],
    'the object a GET caller sends as JSON' => ['{"leaky_key":{"fn":"count"}}'],
]);

it('refuses more measures than the configured limit before the query is built', function (): void {
    config(['core.aggregate.max_measures' => 2]);

    $validate = static fn (): AggregateRequest => aggreq_validated([
        'measures' => [['fn' => 'count'], ['fn' => 'sum', 'field' => 'id'], ['fn' => 'avg', 'field' => 'id']],
    ]);

    expect($validate)->toThrow(static fn (AggregateException $exception) => expect($exception->errorCode)->toBe(AggregateErrorCode::InvalidField)
        ->and($exception->field)->toBe('measures'));
});

it('says that a sort is an object when it arrives as a list', function (mixed $sort): void {
    $validate = static fn (): AggregateRequest => aggreq_validated([
        'measures' => [['fn' => 'count']],
        'group_by' => ['field' => 'group_name'],
        'sort' => $sort,
    ], 'GET');

    expect($validate)->toThrow(static fn (AggregateException $exception) => expect($exception->errorCode)->toBe(AggregateErrorCode::InvalidField)
        ->and($exception->field)->toBe('sort')
        ->and($exception->getMessage())->toContain('must be an object {by, dir}'));
})->with([
    'a plain string, which the list request splits into a list' => ['key'],
    'a comma separated string' => ['key,desc'],
    'a list' => [['measure']],
]);

it('refuses a group and a period given as lists with a message that says they are objects', function (string $key, mixed $value, string $message): void {
    $validate = static fn (): mixed => aggreq_validated(['measures' => [['fn' => 'count']], $key => $value])->aggregateQuery();

    expect($validate)->toThrow(static fn (AggregateException $exception) => expect($exception->errorCode)->toBe(AggregateErrorCode::InvalidField)
        ->and($exception->field)->toBe($key)
        ->and($exception->getMessage())->toContain($message));
})->with([
    'a group given as a list' => ['group_by', ['group_name'], 'must be an object {field, bucket?, label?}'],
    'a period given as a list' => ['period', ['this_month'], 'must be an object {field, and one of last, preset or from with to}'],
]);

it('keeps the keys of an object it is given only as the rules name them', function (): void {
    $query = aggreq_validated([
        'measures' => [['fn' => 'count', 'bogus' => 1]],
        'group_by' => ['field' => 'group_name', 'bogus' => 1],
        'sort' => ['by' => 'key', 'bogus' => 1],
    ])->aggregateQuery();

    expect($query->toArray())->toBe([
        'measures' => [['fn' => 'count']],
        'group_by' => ['field' => 'group_name'],
        'sort' => ['by' => 'key', 'dir' => 'asc'],
    ]);
});

it('refuses what the query refuses on its own rules', function (array $input, string $field): void {
    $request = aggreq_validated(['measures' => [['fn' => 'count']], ...$input]);

    expect($request->aggregateQuery(...))->toThrow(static fn (AggregateException $exception) => expect($exception->errorCode)->toBe(AggregateErrorCode::InvalidField)
        ->and($exception->field)->toBe($field));
})->with([
    'the same measure twice' => [['measures' => [['fn' => 'count'], ['fn' => 'count']]], 'measures.1'],
    'a label with a bucket' => [['group_by' => ['field' => 'created_at', 'bucket' => 'day', 'label' => 'name'], 'period' => ['field' => 'created_at', 'preset' => 'this_month']], 'group_by.label'],
    'a limit with no group' => [['limit' => 3], 'limit'],
    'a limit with a bucketed group' => [['group_by' => ['field' => 'created_at', 'bucket' => 'day'], 'period' => ['field' => 'created_at', 'preset' => 'this_month'], 'limit' => 3], 'limit'],
    'a measure field that is not UTF-8' => [['measures' => [['fn' => 'sum', 'field' => "i\xB1d"]]], 'measures.0.field'],
]);
