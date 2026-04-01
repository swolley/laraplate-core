<?php

declare(strict_types=1);

use Modules\Core\Grids\Components\Grid;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsBreakDeepRootStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsChildModelStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsDatesStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsDeepParentStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsDeletedColumnStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsInvalidRelationStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsInverseChildSourceStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsInverseSourceStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsLocksStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsModelStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsModelWithBrokenRelationStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsModelWithPlainRelationStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsModelWithRelationsStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsPivotRoleStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsPrivateRelationStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsThroughCountryStub;
use Modules\Core\Tests\Stubs\Grids\HasGridUtilsWithoutBelongsStub;

uses(LaravelTestCase::class);

it('returns hidden, fillable and append fields', function (): void {
    $model = new HasGridUtilsModelStub();

    expect($model->getHiddenFields())->toBe(['password'])
        ->and($model->getFillableFields())->toBe(['name', 'email'])
        ->and($model->getAppendFields())->toBe(['full_name'])
        ->and($model->isAppend('full_name'))->toBeTrue();
});

it('returns timestamp columns with and without table prefix', function (): void {
    $model = new HasGridUtilsModelStub();

    $plain = HasGridUtilsModelStub::getTimestampColumns($model);
    $full = HasGridUtilsModelStub::getTimestampColumns($model, true);

    expect($plain['createdAt'])->toBe('created_at')
        ->and($plain['updatedAt'])->toBe('updated_at')
        ->and($full['createdAt'])->toStartWith('users.')
        ->and($full['updatedAt'])->toStartWith('users.');
});

it('returns model casts enriched with fillable and timestamps', function (): void {
    $model = new HasGridUtilsModelStub();
    $casts = $model->getModelCasts();

    expect($casts)->toHaveKey('email_verified_at')
        ->and($casts)->toHaveKey('name')
        ->and($casts)->toHaveKey('email')
        ->and($casts)->toHaveKey('created_at')
        ->and($casts)->toHaveKey('updated_at');
});

it('creates grid instances from model and static helper', function (): void {
    $model = new HasGridUtilsModelStub();

    expect($model->getGrid())->toBeInstanceOf(Grid::class)
        ->and(HasGridUtilsModelStub::grid())->toBeInstanceOf(Grid::class);
});

it('resolves relationships and deep relationship helpers', function (): void {
    $relationships = HasGridUtilsModelWithRelationsStub::getRelationships();
    $single = HasGridUtilsModelWithRelationsStub::getRelationship('roles');
    $deep = HasGridUtilsModelWithRelationsStub::getRelationshipDeeply('hasGridUtilsModelWithRelationsStub.roles');

    expect($relationships)->toHaveKey('roles')
        ->and($single)->not->toBeFalse()
        ->and($single?->getName())->toBe('roles')
        ->and($deep)->toBeArray()
        ->and(count($deep))->toBe(1)
        ->and(HasGridUtilsModelWithRelationsStub::hasRelation('roles'))->toBeTrue()
        ->and(HasGridUtilsModelWithRelationsStub::hasRelationDeeply('hasGridUtilsModelWithRelationsStub.roles'))->toBeTrue()
        ->and(HasGridUtilsModelWithRelationsStub::isDeepRelation('hasGridUtilsModelWithRelationsStub.roles'))->toBeFalse();
});

it('returns columns in plain and typed format', function (): void {
    $model = new HasGridUtilsModelStub();

    $columns = $model->getColumns();
    $typed_columns = $model->getColumns(getTypes: true);

    expect($columns)->toContain('password')
        ->and($columns)->toContain('name')
        ->and($typed_columns)->toHaveKey('name')
        ->and($typed_columns)->not->toHaveKey('created_at')
        ->and($typed_columns['name'])->toBe('string');
});

it('covers relationship negative branches and inverse checks', function (): void {
    expect(HasGridUtilsModelWithRelationsStub::getRelationship('missing_relation'))->toBeFalse()
        ->and(HasGridUtilsModelWithRelationsStub::getInverseRelationship('missing_relation'))->toBeFalse()
        ->and(HasGridUtilsModelWithPlainRelationStub::getInverseRelationship('plain'))->toBeFalse()
        ->and(HasGridUtilsModelWithRelationsStub::getRelationshipDeeply('hasGridUtilsModelWithRelationsStub.missing'))->toBeFalse()
        ->and(HasGridUtilsModelWithRelationsStub::getInverseRelationshipDeeply('hasGridUtilsModelWithRelationsStub.missing'))->toBeFalse();
});

it('covers relationship lookup by local foreign key and broken relation catch', function (): void {
    $by_fk = HasGridUtilsChildModelStub::getRelationshipByLocalForeignKey('any_key');
    $broken = HasGridUtilsModelWithBrokenRelationStub::getRelationship('broken');

    expect($by_fk)->not->toBeNull()
        ->and($by_fk?->getName())->toBe('user')
        ->and($broken)->toBeFalse();
});

it('covers columns filtering variants', function (): void {
    $model = new HasGridUtilsModelStub();
    $visible_only = $model->getColumns(filterVisible: true);
    $writable_only = $model->getColumns(filterWritable: true);

    expect($visible_only)->not->toContain('password')
        ->and($writable_only)->not->toContain('name')
        ->and($writable_only)->not->toContain('email');
});

it('covers deep inverse relationship resolution', function (): void {
    $inverse = HasGridUtilsDeepParentStub::getInverseRelationshipDeeply('hasGridUtilsDeepParentStub.children.leaves');

    expect($inverse)->toBeFalse();
});

it('covers append accessor helper methods', function (): void {
    $model = new HasGridUtilsModelStub();

    expect($model->hasGetAppend('full_name'))->toBeTrue()
        ->and($model->hasSetAppend('full_name'))->toBeFalse();
});

it('covers non public and invalid relationship branches', function (): void {
    expect(HasGridUtilsInvalidRelationStub::getRelationship('notARelation'))->toBeFalse();
});

it('covers pivot and has-many-through relation metadata extraction', function (): void {
    $pivot = HasGridUtilsPivotRoleStub::getRelationship('permissions');
    $through = HasGridUtilsThroughCountryStub::getRelationship('posts');

    expect($pivot)->not->toBeFalse()
        ->and($pivot)->toBeInstanceOf(Modules\Core\Grids\Definitions\PivotRelationInfo::class)
        ->and($through)->not->toBeFalse()
        ->and($through?->getName())->toBe('posts');
});

it('covers getModelCasts dates and locked timestamp branches', function (): void {
    app()->instance('locked', new class
    {
        public function getLockedColumnName(): string
        {
            return 'locked_at';
        }
    });

    $dates = new HasGridUtilsDatesStub();
    $locks = new HasGridUtilsLocksStub();
    $timestamps = HasGridUtilsLocksStub::getTimestampColumns($locks);

    expect($dates->getModelCasts())->toHaveKey('legacy_date')
        ->and($dates->getModelCasts()['legacy_date'])->toBe('date')
        ->and($timestamps['lockedAt'])->toBe('locked_at');
});

it('covers non public relation guard branch', function (): void {
    expect(HasGridUtilsPrivateRelationStub::getRelationship('hiddenRel'))->toBeFalse();
});

it('covers inverse relationship empty branch', function (): void {
    expect(HasGridUtilsInverseSourceStub::getInverseRelationship('targets'))->toBeFalse();
});

it('covers deep inverse relationship break branch', function (): void {
    $inverse = HasGridUtilsBreakDeepRootStub::getInverseRelationshipDeeply('hasGridUtilsBreakDeepRootStub.children.leaves');

    expect($inverse)->toBeFalse();
});

it('covers deep inverse early false branch', function (): void {
    expect(HasGridUtilsBreakDeepRootStub::getInverseRelationshipDeeply('hasGridUtilsBreakDeepRootStub.missing.deep'))->toBeFalse();
});

it('covers deleted-at timestamp discovery branch', function (): void {
    $timestamps = HasGridUtilsDeletedColumnStub::getTimestampColumns(new HasGridUtilsDeletedColumnStub(), true);

    expect($timestamps['deletedAt'])->toBe('deleted_col.deleted_at');
});

it('covers inverse relationship branch using class hierarchy check', function (): void {
    $inverse = HasGridUtilsInverseChildSourceStub::getInverseRelationship('targets');

    expect($inverse)->not->toBeFalse()
        ->and($inverse?->getName())->toBe('source');
});

it('returns null when no belongs relation exists for local foreign key lookup', function (): void {
    expect(HasGridUtilsWithoutBelongsStub::getRelationshipByLocalForeignKey('source_id'))->toBeNull();
});
