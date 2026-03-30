<?php

declare(strict_types=1);

use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\SelectRequestData;
use Modules\Core\Casts\Sort;
use Modules\Core\Casts\SortDirection;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Http\Requests\ListRequest;
use Modules\Core\Http\Requests\SelectRequest;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('select request data conforms columns and relations', function (): void {
    $request = new class extends SelectRequest {};

    $data = new SelectRequestData($request, 'setting', [
        'columns' => ['id', ['name' => 'name', 'type' => 'column']],
        'relations' => ['setting.translations', 'owner'],
    ], 'id');

    expect($data->columns[0]->name)->toBe('setting.id')
        ->and($data->columns[1]->name)->toBe('setting.name')
        ->and($data->relations)->toBe(['translations', 'owner']);
});

it('select request data keeps already prefixed columns unchanged', function (): void {
    $request = new class extends SelectRequest {};

    $data = new SelectRequestData($request, 'setting', [
        'columns' => ['setting.created_at'],
        'relations' => [],
    ], 'id');

    expect($data->columns[0]->name)->toBe('setting.created_at');
});

it('list request data extracts pagination, filters, groups and sorts from pagination payload', function (): void {
    $request = new ListRequest();

    $data = new ListRequestData($request, 'setting', [
        'pagination' => 10,
        'page' => 2,
        'count' => true,
        'columns' => ['id'],
        'sort' => ['name', ['property' => 'created_at', 'direction' => 'desc']],
        'filters' => [
            'operator' => 'or',
            'filters' => [
                ['property' => 'name', 'operator' => 'like', 'value' => 'john'],
                ['property' => 'status', 'operator' => 'in', 'value' => 'a,b'],
                ['property' => 'deleted_at', 'value' => 'null'],
            ],
        ],
        'group_by' => ['setting.name', 'setting.id'],
    ], 'id');

    expect($data->pagination)->toBe(10)
        ->and($data->page)->toBe(2)
        ->and($data->skip)->toBe(10)
        ->and($data->from)->toBe(11)
        ->and($data->to)->toBe(21)
        ->and($data->count)->toBeTrue()
        ->and($data->sort[0])->toBeInstanceOf(Sort::class)
        ->and($data->sort[1]->direction)->toBe(SortDirection::DESC)
        ->and($data->filters)->toBeInstanceOf(FiltersGroup::class)
        ->and($data->filters->operator)->toBe(WhereClause::OR)
        ->and($data->group_by)->toBe(['name', 'id']);
});

it('list request data handles from-to and limit pagination strategies', function (): void {
    $request = new ListRequest();

    $from_to = new ListRequestData($request, 'setting', [
        'from' => 3,
        'to' => 9,
        'sort' => [],
    ], 'id');

    $limit = new ListRequestData($request, 'setting', [
        'limit' => 5,
        'sort' => [],
    ], 'id');

    expect($from_to->from)->toBe(3)
        ->and($from_to->skip)->toBe(2)
        ->and($from_to->take)->toBe(6)
        ->and($limit->limit)->toBe(5)
        ->and($limit->pagination)->toBe(5);
});

it('list request data merges filters and computes total pages', function (): void {
    Setting::query()->create(['name' => 'pagination', 'value' => '7']);
    $request = new ListRequest();
    $data = new ListRequestData($request, 'setting', ['sort' => []], 'id');

    $existing = new FiltersGroup([new Filter('name', 'john', FilterOperator::EQUALS)], WhereClause::AND);
    $data->mergeFilters($existing);
    $data->mergeFilters(new FiltersGroup([new Filter('active', true, FilterOperator::EQUALS)], WhereClause::OR));

    expect($data->filters)->toBeInstanceOf(FiltersGroup::class)
        ->and($data->pagination)->toBeGreaterThanOrEqual(1)
        ->and($data->calculateTotalPages(20))->toBeGreaterThanOrEqual(1);
});

it('list request data wraps top-level list filters into a FiltersGroup', function (): void {
    $request = new ListRequest();

    $data = new ListRequestData($request, 'setting', [
        'sort' => [],
        'filters' => [
            ['property' => 'name', 'value' => 'john'],
            ['property' => 'active', 'value' => true, 'operator' => 'eq'],
        ],
    ], 'id');

    expect($data->filters)->toBeInstanceOf(FiltersGroup::class)
        ->and($data->filters->filters)->toHaveCount(2);
});
