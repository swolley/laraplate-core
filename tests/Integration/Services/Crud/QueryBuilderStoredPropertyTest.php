<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Services\Crud\ModelPropertyResolver;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\Core\Tests\Stubs\Crud\DynamicOnlyStubModel;

beforeEach(function (): void {
    Schema::create('dynamic_only_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->json('components')->nullable();
        $table->unsignedBigInteger('entity_id')->nullable();
        $table->unsignedBigInteger('presettable_id')->nullable();
    });
    SchemaInspector::getInstance()->clearTable('dynamic_only_stub');

    DB::table('dynamic_only_stub')->insert([
        ['name' => 'Red thing', 'components' => json_encode(['color' => 'red', 'size' => 'L'])],
        ['name' => 'Blue thing', 'components' => json_encode(['color' => 'blue'])],
        ['name' => 'Plain thing', 'components' => null],
    ]);
});

afterEach(function (): void {
    Schema::dropIfExists('dynamic_only_stub');
    SchemaInspector::getInstance()->clearTable('dynamic_only_stub');
});

function dynamic_only_names(Filter|FiltersGroup $filter): array
{
    $query = DynamicOnlyStubModel::query();
    (new QueryBuilder)->applyFilters($query, $filter instanceof FiltersGroup ? $filter : new FiltersGroup([$filter]));

    return $query->orderBy('id')->pluck('name')->all();
}

it('classifies the properties of a model with only dynamic contents', function (): void {
    $model = new DynamicOnlyStubModel;

    expect(ModelPropertyResolver::resolve($model, 'color'))->toBe(ModelPropertyResolver::DYNAMIC)
        ->and(ModelPropertyResolver::resolve($model, 'name'))->toBeNull()
        ->and(ModelPropertyResolver::mainContainers($model))->toBe(['components'])
        ->and(ModelPropertyResolver::translationContainer($model))->toBeNull();
});

it('filters on a dynamic field of the components column', function (): void {
    expect(dynamic_only_names(new Filter('color', 'red', FilterOperator::Equals)))->toBe(['Red thing'])
        ->and(dynamic_only_names(new Filter('color', ['red', 'blue'], FilterOperator::In)))->toBe(['Red thing', 'Blue thing'])
        ->and(dynamic_only_names(new Filter('size', '%L%', FilterOperator::Like)))->toBe(['Red thing']);
});

it('matches nothing for a dynamic field no record has, and still filters columns', function (): void {
    expect(dynamic_only_names(new Filter('colour', 'red', FilterOperator::Equals)))->toBe([])
        ->and(dynamic_only_names(new Filter('name', 'Plain thing', FilterOperator::Equals)))->toBe(['Plain thing']);
});

it('keeps the dynamic condition inside its OR group', function (): void {
    $group = new FiltersGroup([
        new Filter('color', 'blue', FilterOperator::Equals),
        new Filter('name', 'Plain thing', FilterOperator::Equals),
    ], WhereClause::Or);

    expect(dynamic_only_names($group))->toBe(['Blue thing', 'Plain thing']);
});
