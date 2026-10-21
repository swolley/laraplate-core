<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Casts\Column;
use Modules\Core\Casts\ColumnType;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\Sort;
use Modules\Core\Casts\SortDirection;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\Core\Tests\Stubs\Crud\MountingNameStubModel;

beforeEach(function (): void {
    Schema::create('plain_target_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
    });
    Schema::create('mounting_name_stub', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('target_id')->nullable();
        $table->foreign('target_id')->references('id')->on('plain_target_stub');
    });
    SchemaInspector::getInstance()->clearTable('plain_target_stub');
    SchemaInspector::getInstance()->clearTable('mounting_name_stub');

    DB::table('plain_target_stub')->insert([['name' => 'Charlie'], ['name' => 'Alpha'], ['name' => 'Bravo']]);
    // Row ids follow the insertion order: 1 -> Charlie, 2 -> Alpha, 3 -> Bravo, 4 -> no target.
    DB::table('mounting_name_stub')->insert([['target_id' => 1], ['target_id' => 2], ['target_id' => 3], ['target_id' => null]]);
});

afterEach(function (): void {
    Schema::dropIfExists('mounting_name_stub');
    Schema::dropIfExists('plain_target_stub');
    SchemaInspector::getInstance()->clearTable('mounting_name_stub');
    SchemaInspector::getInstance()->clearTable('plain_target_stub');
});

/**
 * @param  array<int,Sort>  $sort
 * @param  array<int,Column>  $columns
 */
function relation_sort_rows(array $sort, array $columns = []): Illuminate\Support\Collection
{
    $model = new MountingNameStubModel;
    $data = (new ReflectionClass(ListRequestData::class))->newInstanceWithoutConstructor();

    foreach ([
        'request' => new Request,
        'mainEntity' => $model->getTable(),
        'primaryKey' => 'id',
        'connection' => null,
        'model' => $model,
        'columns' => $columns,
        'relations' => [],
        'sort' => $sort,
        'filters' => null,
        'group_by' => [],
        'pagination' => 25,
        'page' => null,
        'skip' => null,
        'take' => null,
        'from' => null,
        'to' => null,
        'limit' => null,
        'count' => false,
    ] as $property => $value) {
        (new ReflectionProperty($data, $property))->setValue($data, $value);
    }

    $query = MountingNameStubModel::query();
    (new QueryBuilder)->prepareQuery($query, $data);

    return $query->get();
}

it('orders the rows of the main model by a column of a to-one relation', function (): void {
    $ascending = relation_sort_rows([new Sort('target.name', SortDirection::Asc)])->pluck('id')->all();
    $descending = relation_sort_rows([new Sort('target.name', SortDirection::Desc)])->pluck('id')->all();

    // Alpha (2), Bravo (3), Charlie (1); the row without a target sorts as null.
    expect(array_values(array_diff($ascending, [4])))->toBe([2, 3, 1])
        ->and(array_values(array_diff($descending, [4])))->toBe([1, 3, 2]);
});

it('orders by an attribute the model mounts from its relation as if it were its own column', function (): void {
    $ascending = relation_sort_rows([new Sort('name', SortDirection::Asc)])->pluck('id')->all();

    expect(array_values(array_diff($ascending, [4])))->toBe([2, 3, 1]);
});

it('reads a mounted attribute requested as a column from the mounted relation', function (): void {
    $rows = relation_sort_rows(
        [new Sort('name', SortDirection::Asc)],
        [new Column('mounting_name_stub.id', ColumnType::Column), new Column('mounting_name_stub.name', ColumnType::Column)],
    );

    $named = $rows->filter(fn (MountingNameStubModel $row): bool => $row->relationLoaded('target') && $row->target !== null);

    expect($named->map(fn (MountingNameStubModel $row): string => $row->target->name)->values()->all())->toBe(['Alpha', 'Bravo', 'Charlie']);
});

it('does not order by a column the related table does not have', function (): void {
    $rows = relation_sort_rows([new Sort('target.no_such_column', SortDirection::Asc)]);

    expect($rows)->toHaveCount(4);
});
