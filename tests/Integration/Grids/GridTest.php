<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Modules\Core\Grids\Components\Grid;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Models\User;
use Modules\Core\Tests\Stubs\GridUtilsModelStub;

class GridConnectionAffinityModel extends Model
{
    use HasGridUtils;

    protected $connection = 'grid_layout_affinity';
}

it('detects models that use HasGridUtils', function (): void {
    expect(Grid::useGridUtils(User::factory()->make()))->toBeFalse()
        ->and(Grid::useGridUtils(new GridUtilsModelStub))->toBeTrue();
});

it('initializes grid with a model class that uses HasGridUtils', function (): void {
    $grid = new Grid(GridUtilsModelStub::class);

    expect($grid->getModelName())->toBe('GridUtilsModelStub')
        ->and($grid->getFullModelName())->toBe(GridUtilsModelStub::class);
});

it('checks the layouts table on the model connection', function (): void {
    $manager = app('db');

    config()->set('database.connections.grid_layout_affinity', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);

    $manager->purge('grid_layout_affinity');
    $manager->connection('grid_layout_affinity')->getSchemaBuilder()->create(
        Grid::LAYOUTS_TABLE,
        fn (Blueprint $table) => $table->id(),
    );
    SchemaInspector::reset();

    try {
        $method = new ReflectionMethod(Grid::class, 'checkGridLayoutsTableExists');

        expect($method->invoke(new Grid(GridConnectionAffinityModel::class)))->toBeTrue();
    } finally {
        SchemaInspector::reset();
        $manager->purge('grid_layout_affinity');
    }
});
