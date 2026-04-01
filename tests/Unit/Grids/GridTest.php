<?php

declare(strict_types=1);

use Modules\Core\Grids\Components\Grid;
use Modules\Core\Models\User;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\GridUtilsModelStub;

uses(LaravelTestCase::class);

it('detects models that use HasGridUtils', function (): void {
    expect(Grid::useGridUtils(User::factory()->make()))->toBeFalse()
        ->and(Grid::useGridUtils(new GridUtilsModelStub))->toBeTrue();
});

it('initializes grid with a model class that uses HasGridUtils', function (): void {
    $grid = new Grid(GridUtilsModelStub::class);

    expect($grid->getModelName())->toBe('GridUtilsModelStub')
        ->and($grid->getFullModelName())->toBe(GridUtilsModelStub::class);
});
