<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\IParsableRequest;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Grids\Casts\GridRequestData;
use Modules\Core\Grids\Components\Field;
use Modules\Core\Grids\Definitions\Entity;
use Modules\Core\Grids\Definitions\FieldType;
use Modules\Core\Grids\Definitions\Relation;
use Modules\Core\Grids\Definitions\RelationInfo;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\Grids\EntityHarness;
use Modules\Core\Tests\Stubs\Grids\EntityLocksStub;
use Modules\Core\Tests\Stubs\Grids\EntityModelStub;
use Modules\Core\Tests\Stubs\Grids\EntityNestedChildStub;
use Modules\Core\Tests\Stubs\Grids\EntityNestedLeafStub;
use Modules\Core\Tests\Stubs\Grids\EntityNestedParentStub;
use Modules\Core\Tests\Stubs\Grids\EntityNoTimestampsStub;
use Modules\Core\Tests\Stubs\Grids\EntityPlainModelStub;
use Modules\Core\Tests\Stubs\Grids\EntityPlainNonFinalModelStub;
use Modules\Core\Tests\Stubs\Grids\EntitySoftDeleteForceStub;
uses(LaravelTestCase::class);

it('covers base entity model and table metadata access', function (): void {
    $entity = new EntityHarness(new EntityModelStub());

    expect($entity->getTable())->toBe('users')
        ->and($entity->getModelName())->toBe('EntityModelStub')
        ->and($entity->getFullModelName())->toBe(EntityModelStub::class)
        ->and($entity->getAllTables()->all())->toBe(['users'])
        ->and($entity->getAllModels()->count())->toBe(1);
});

it('adds and resolves fields on entity', function (): void {
    $entity = new EntityHarness(new EntityModelStub());
    $field = new Field('', 'email', null, FieldType::COLUMN, new EntityModelStub());

    expect($entity->seedField($field))->toBeTrue()
        ->and($entity->seedField($field))->toBeFalse()
        ->and($entity->hasField($field))->toBeTrue()
        ->and($entity->hasFieldDeeply($field))->toBeTrue()
        ->and($entity->getField($field))->toBeInstanceOf(Field::class)
        ->and($entity->getAllFields()->count())->toBe(1)
        ->and($entity->hasFields())->toBeTrue()
        ->and($entity->toArray())->toHaveKey('fields');
});

it('adds and removes relations deeply', function (): void {
    $entity = new EntityHarness(new EntityModelStub());
    $relation_info = new RelationInfo('hasMany', 'roles', EntityModelStub::class, 'roles', 'user_id', 'id');
    $relation = new Relation('', $relation_info);

    expect($entity->seedRelation($relation))->toBeTrue()
        ->and($entity->hasRelations())->toBeTrue()
        ->and($entity->hasRelation('roles'))->toBeTrue()
        ->and($entity->getRelation('roles'))->toBeInstanceOf(Relation::class)
        ->and($entity->removeRelationByName('roles'))->toBeTrue()
        ->and($entity->hasRelations())->toBeFalse();
});

it('constructs relation with predefined fields list', function (): void {
    $model = new EntityModelStub();
    $field = new Field('', 'email', null, FieldType::COLUMN, $model);
    $relation_info = new RelationInfo('hasMany', 'roles', EntityModelStub::class, 'roles', 'user_id', 'id');
    $relation = new Relation('', $relation_info, [$field]);

    expect($relation->getFields()->count())->toBe(1)
        ->and($relation->hasField($field))->toBeTrue();
});

it('builds deep relations from root entity without forcing typed errors', function (): void {
    $entity = new EntityHarness(new EntityModelStub());
    $list = [
        new RelationInfo('hasMany', 'roles', EntityModelStub::class, 'roles', 'user_id', 'id'),
        new RelationInfo('hasMany', 'permissions', EntityModelStub::class, 'permissions', 'role_id', 'id'),
    ];

    $leaf = $entity->addRelationDeeply($list);

    expect($leaf)->toBeInstanceOf(Relation::class)
        ->and($entity->hasRelation('roles'))->toBeTrue();
});

it('applies where methods for null and like operators', function (): void {
    $query = EntityModelStub::query();
    EntityHarness::applyWhere($query, 'email', FilterOperator::LIKE, 'John');
    EntityHarness::applyWhere($query, 'email_verified_at', FilterOperator::EQUALS, null);
    EntityHarness::applyWhere($query, 'deleted_at', FilterOperator::NOT_EQUALS, null, WhereClause::OR);

    $sql = $query->toSql();

    expect($sql)->toContain('LOWER(email)')
        ->and($sql)->toContain('is null')
        ->and($sql)->toContain('or')
        ->and($sql)->toContain('is not null');
});

it('covers helper methods for timestamps sorts and relation cleanup', function (): void {
    $entity = new EntityHarness(new EntityModelStub());
    $model = new EntityModelStub();
    $relation_info = new RelationInfo('hasMany', 'roles', EntityModelStub::class, 'roles', 'user_id', 'id');
    $empty_relation = new Relation('', $relation_info);
    $entity->seedRelation($empty_relation);

    $timestamps = $entity->getTimestampsColumnsPublic();
    $sorts = $entity->defaultSortsPublic(['id', 'name', 'email'], $model);
    $removed = $entity->removeUnusedRelationsPublic();

    expect($timestamps)->toContain('created_at')
        ->and($timestamps)->toContain('updated_at')
        ->and(array_values($sorts))->toBe([
            ['property' => 'name', 'direction' => 'asc'],
            ['property' => 'email', 'direction' => 'asc'],
        ])
        ->and($removed)->toBeTrue()
        ->and($entity->hasRelations())->toBeFalse()
        ->and($entity->hasDeepRelationsPublic())->toBeFalse();
});

it('covers relation and field deep lookup negative branches', function (): void {
    $entity = new EntityHarness(new EntityModelStub());
    $relation_info = new RelationInfo('hasMany', 'roles', EntityModelStub::class, 'roles', 'user_id', 'id');
    $relation = new Relation('', $relation_info);
    $entity->seedRelation($relation);

    expect($entity->getRelationDeeply('users.missing'))->toBeNull()
        ->and($entity->hasRelationDeeply('missing'))->toBeFalse()
        ->and($entity->getFieldDeeply('users.missing_column'))->toBeNull()
        ->and($entity->removeRelationByName('missing'))->toBeFalse();
});

it('covers setFields and addFields helper methods', function (): void {
    $entity = new EntityHarness(new EntityModelStub());
    $field = new Field('', 'email', null, FieldType::COLUMN, new EntityModelStub());

    $entity->setFieldsPublic([$field]);
    expect($entity->hasField($field))->toBeTrue();

    expect(fn () => $entity->addFieldsPublic([
        'users.name' => static fn (Model $model): Field => new Field('', 'name', null, FieldType::COLUMN, $model),
    ]))->toThrow(Error::class);
});

it('covers current entity and primary key helpers', function (): void {
    $entity = new EntityHarness(new EntityModelStub());

    expect($entity->isCurrentEntityPublic(''))->toBeTrue()
        ->and(fn () => $entity->isCurrentEntityPublic('entityHarness'))->toThrow(Error::class)
        ->and($entity->getPrimaryKeyPublic())->toBe('id')
        ->and($entity->getFullPrimaryKeyPublic())->toBe('users.id');
});

it('covers soft-delete and query helper methods', function (): void {
    $entity = new EntityHarness(new EntityModelStub());
    $query = EntityModelStub::query();

    $entity->addSortsIntoQueryPublic($query, [
        ['property' => 'users.email', 'direction' => 'asc'],
        ['property' => 'name', 'direction' => 'desc'],
    ]);

    $columns = $entity->checkColumnsOrGetDefaultsPublic(new EntityModelStub(), 'email', null);

    expect($entity->hasSoftDeletePublic())->toBeTrue()
        ->and($query->toSql())->toContain('order by "email" asc, "name" desc')
        ->and($columns)->toContain('email');
});

it('throws for invalid model string and for model without grid utils', function (): void {
    expect(fn () => new EntityHarness(stdClass::class))->toThrow(UnexpectedValueException::class);

    config(['core.dynamic_gridutils' => false]);
    expect(fn () => new EntityHarness(new class extends Model {}))->toThrow(UnexpectedValueException::class);
});

it('covers dynamic model extension branch when grid utils is enabled', function (): void {
    config(['core.dynamic_gridutils' => true]);

    $entity = new EntityHarness(new EntityPlainNonFinalModelStub());

    expect($entity->getModel())->toBeInstanceOf(Model::class);
});

it('covers relation deep traversal with initialized root name', function (): void {
    $entity = new EntityHarness(new EntityModelStub());
    $entity->setPathAndName('', 'entityHarness');
    $relation_info = new RelationInfo('hasMany', 'roles', EntityModelStub::class, 'roles', 'user_id', 'id');
    $relation = new Relation('', $relation_info);
    $entity->seedRelation($relation);

    expect($entity->getRelationDeeply('entityModelStub.roles'))->toBeInstanceOf(Relation::class)
        ->and($entity->getAllFullRelationsNames()->count())->toBe(1);
});

it('covers explicit columns branch in checkColumnsOrGetDefaults', function (): void {
    $entity = new EntityHarness(new EntityModelStub());
    $columns = $entity->checkColumnsOrGetDefaultsPublic(new EntityModelStub(), 'email', ['name']);

    expect($columns[0])->toBe('email')
        ->and($columns)->toContain('name');
});

it('covers in operator branch in applyCorrectWhereMethod', function (): void {
    $query = EntityModelStub::query();
    EntityHarness::applyWhere($query, 'id', FilterOperator::IN, [1, 2, 3]);

    expect($query->toSql())->toContain('in (?, ?, ?)');
});

it('covers nested relations traversal branches', function (): void {
    $entity = new EntityHarness(new EntityNestedParentStub());
    $entity->setPathAndName('', 'entityNestedParentStub');
    $relations = [
        new RelationInfo('hasMany', 'children', EntityNestedChildStub::class, 'nested_child', 'parent_id', 'id'),
        new RelationInfo('hasMany', 'leaves', EntityNestedLeafStub::class, 'nested_leaf', 'child_id', 'id'),
    ];

    $leaf = $entity->addRelationDeeply($relations);

    expect($leaf)->toBeInstanceOf(Relation::class)
        ->and($entity->hasRelation('children'))->toBeTrue()
        ->and($entity->getAllTables())->toContain('nested_child')
        ->and($entity->getAllModels()->count())->toBe(3)
        ->and($entity->getAllFullRelationsNames()->count())->toBeGreaterThanOrEqual(1);
});

it('covers addField relation path branch with addRelationField', function (): void {
    $entity = new EntityHarness(new EntityNestedParentStub());
    $entity->setPathAndName('', 'entityNestedParentStub');
    $field = new Field('entityNestedParentStub.children', 'name', null, FieldType::COLUMN, new EntityNestedChildStub());

    expect($entity->seedField($field))->toBeFalse()
        ->and($entity->hasRelation('children'))->toBeTrue();
});

it('covers where like branch without auto wrapping percents', function (): void {
    $query = EntityModelStub::query();
    EntityHarness::applyWhere($query, 'email', FilterOperator::LIKE, '%john');

    expect($query->getBindings()[0])->toBe('%john');
});

it('covers no timestamps and forced soft delete timestamp branches', function (): void {
    $entity = new EntityHarness(new EntityNoTimestampsStub());
    $none = $entity->getTimestampsColumnsPublic();

    $soft = new EntitySoftDeleteForceStub();
    $prop = new ReflectionProperty(EntitySoftDeleteForceStub::class, 'forceDeleting');
    $prop->setAccessible(true);
    $prop->setValue($soft, true);

    $entity_soft = new EntityHarness($soft);
    $stamps_soft = $entity_soft->getTimestampsColumnsPublic();

    expect($none)->toBe([])
        ->and($entity_soft->hasSoftDeletePublic())->toBeFalse()
        ->and($stamps_soft)->toContain('deleted_at');
});

it('covers direct and nested field lookup branches', function (): void {
    $entity = new EntityHarness(new EntityNestedParentStub());
    $entity->setPathAndName('', 'entityNestedParentStub');

    $top_field = new Field('', 'title', null, FieldType::COLUMN, new EntityNestedParentStub());
    $entity->seedField($top_field);

    $relations = [
        new RelationInfo('hasMany', 'children', EntityNestedChildStub::class, 'nested_child', 'parent_id', 'id'),
    ];
    $entity->addRelationDeeply($relations);

    $child_field = new Field('entityNestedParentStub.children', 'name', null, FieldType::COLUMN, new EntityNestedChildStub());
    $entity->getRelation('children')?->addField($child_field);

    expect($entity->getFieldDeeply($top_field))->toBe($top_field)
        ->and($entity->getFieldDeeply('entityNestedParentStub..name'))->toBeNull()
        ->and($entity->getFieldDeeply('entityNestedParentStub.entityNestedParentStub.children.name'))->toBeNull()
        ->and($entity->getFieldDeeply('entityNestedParentStub.children.name'))->toBeInstanceOf(Field::class)
        ->and($entity->hasFieldDeeply('entityNestedParentStub.children.name'))->toBeTrue();
});

it('covers all fields/query fields merge and deep flags', function (): void {
    $entity = new EntityHarness(new EntityNestedParentStub());
    $entity->setPathAndName('', 'entityNestedParentStub');

    $entity->seedField(new Field('', 'local_column', null, FieldType::COLUMN, new EntityNestedParentStub()));
    $relations = [
        new RelationInfo('hasMany', 'children', EntityNestedChildStub::class, 'nested_child', 'parent_id', 'id'),
    ];
    $entity->addRelationDeeply($relations);
    $relation = $entity->getRelation('children');
    $relation?->addField(new Field('entityNestedParentStub.children', 'remote_column', null, FieldType::COLUMN, new EntityNestedChildStub()));
    $relation?->addField(new Field('entityNestedParentStub.children', 'computed', null, FieldType::APPEND, new EntityNestedChildStub()));
    $relation?->addField(new Field('entityNestedParentStub.children', 'method_column', null, FieldType::METHOD, new EntityNestedChildStub()));

    expect($entity->getAllFields()->count())->toBeGreaterThanOrEqual(2)
        ->and($entity->getAllQueryFields()->keys()->join(','))->toContain('entityNestedParentStub.children.remote_column')
        ->and($entity->getAllQueryFields()->keys()->join(','))->not->toContain('computed')
        ->and($entity->hasDeepFields())->toBeTrue()
        ->and($entity->hasFieldsDeeply())->toBeTrue();
});

it('covers relation deep traversal and recursive remove branches', function (): void {
    $entity = new EntityHarness(new EntityNestedParentStub());
    $entity->setPathAndName('root', 'entityNestedParentStub');

    $relations = [
        new RelationInfo('hasMany', 'children', EntityNestedChildStub::class, 'nested_child', 'parent_id', 'id'),
        new RelationInfo('hasMany', 'leaves', EntityNestedLeafStub::class, 'nested_leaf', 'child_id', 'id'),
    ];
    $entity->addRelationDeeply($relations);

    expect($entity->getRelationDeeply('root.children'))->toBeInstanceOf(Relation::class)
        ->and($entity->getRelationDeeply('root.children.leaves'))->toBeInstanceOf(Relation::class)
        ->and($entity->hasDeepRelationsPublic())->toBeTrue()
        ->and($entity->hasRelationDeeply('leaves'))->toBeTrue()
        ->and($entity->removeRelationByName('leaves'))->toBeTrue()
        ->and($entity->hasRelationDeeply('leaves'))->toBeFalse();
});

it('covers deep field false/delegate and deep relation second-branch checks', function (): void {
    $entity = new EntityHarness(new EntityNestedParentStub());
    $entity->setPathAndName('', 'entityNestedParentStub');
    $entity->addRelationDeeply([
        new RelationInfo('hasMany', 'children', EntityNestedChildStub::class, 'nested_child', 'parent_id', 'id'),
    ]);

    expect($entity->hasDeepFields())->toBeFalse();

    $entity->getRelation('children')?->addField(new Field('entityNestedParentStub.children', 'only_remote', null, FieldType::COLUMN, new EntityNestedChildStub()));
    expect($entity->hasFieldsDeeply())->toBeTrue();

    $fake_relation = new class
    {
        public function hasRelations(): bool
        {
            return false;
        }

        public function hasDeepRelations(): bool
        {
            return true;
        }
    };

    $relations_property = new ReflectionProperty(Entity::class, 'relations');
    $relations_property->setAccessible(true);
    $relations_property->setValue($entity, collect([$fake_relation]));

    expect($entity->hasDeepRelationsPublic())->toBeTrue();
});

it('covers duplicate relation insertion and relation reuse in addRelationDeeply', function (): void {
    $entity = new EntityHarness(new EntityNestedParentStub());
    $entity->setPathAndName('', 'entityNestedParentStub');
    $relation_info = new RelationInfo('hasMany', 'children', EntityNestedChildStub::class, 'nested_child', 'parent_id', 'id');
    $relation = new Relation('', $relation_info);

    $entity->seedRelation($relation);
    expect($entity->seedRelation($relation))->toBeFalse();

    $first = $entity->addRelationDeeply([$relation_info]);
    $second = $entity->addRelationDeeply([$relation_info]);
    expect($first)->toBe($second);
});

it('covers setFields propagation and addFields closure branches', function (): void {
    $entity = new EntityHarness(new EntityNestedParentStub());
    $entity->setPathAndName('', 'entityNestedParentStub');
    $entity->addRelationDeeply([
        new RelationInfo('hasMany', 'children', EntityNestedChildStub::class, 'nested_child', 'parent_id', 'id'),
    ]);

    $fields = collect([
        'title' => new Field('', 'title', null, FieldType::COLUMN, new EntityNestedParentStub()),
        'entityNestedParentStub.children.remote' => new Field('entityNestedParentStub.children', 'remote', null, FieldType::COLUMN, new EntityNestedChildStub()),
    ]);
    $entity->setFieldsPublic($fields);

    $entity->addFieldsPublic([
        'entityNestedParentStub.generated' => static fn (Model $model): Field => new Field('', 'generated', null, FieldType::COLUMN, $model),
    ]);

    expect($entity->getFields()->has('generated'))->toBeTrue();

    expect(fn () => $entity->addFieldsPublic([
        'other.path.broken' => static fn (Model $model): Field => new Field('', 'broken', null, FieldType::COLUMN, $model),
    ]))->toThrow(Exception::class);
});

it('covers parseRequest assignment branch and lock timestamps branch', function (): void {
    app()->instance('locked', new class
    {
        public function getLockedColumnName(): string
        {
            return 'locked_at';
        }
    });

    $entity = new EntityHarness(new EntityLocksStub());
    $data = (new ReflectionClass(GridRequestData::class))->newInstanceWithoutConstructor();
    $fake_request = new class($data) implements IParsableRequest
    {
        public function __construct(private readonly GridRequestData $data) {}

        public function parsed(): Modules\Core\Casts\CrudRequestData
        {
            return $this->data;
        }
    };

    $entity->parseRequestPublic($fake_request);

    expect($entity->getRequestDataPublic())->toBe($data)
        ->and($entity->getTimestampsColumnsPublic())->toContain('locked_at');
});

it('covers dynamic eval branch for generated gridutils model', function (): void {
    config([
        'core.dynamic_gridutils' => true,
        'core.extended_class_suffix' => 'GridUtils' . uniqid('X', false),
    ]);

    $entity = new EntityHarness(new EntityPlainNonFinalModelStub());

    expect(class_uses_recursive($entity->getModel()))->toContain(HasGridUtils::class);
});

it('covers setDataIntoResponse pagination and range branches', function (): void {
    $entity = new EntityHarness(new EntityModelStub());
    $ref = new ReflectionProperty(EntityHarness::class, 'requestData');
    $ref->setAccessible(true);

    $request_page = request()->duplicate(['page' => 3, 'pagination' => 15]);
    $data_page = (new ReflectionClass(GridRequestData::class))->newInstanceWithoutConstructor();
    $request_prop = new ReflectionProperty(Modules\Core\Casts\CrudRequestData::class, 'request');
    $request_prop->setAccessible(true);
    $request_prop->setValue($data_page, $request_page);
    $ref->setValue($entity, $data_page);

    $builder_page = new ResponseBuilder($request_page);
    $entity->setDataIntoResponsePublic($builder_page, collect([['id' => 1]]), 1);

    expect($builder_page->getCurrentPage())->toBe(3)
        ->and($builder_page->getPagination())->toBe(15);

    $request_range = request()->duplicate(['from' => 2, 'to' => 8]);
    $data_range = (new ReflectionClass(GridRequestData::class))->newInstanceWithoutConstructor();
    $request_prop->setValue($data_range, $request_range);
    $from_prop = new ReflectionProperty(Modules\Core\Casts\ListRequestData::class, 'from');
    $to_prop = new ReflectionProperty(Modules\Core\Casts\ListRequestData::class, 'to');
    $from_prop->setAccessible(true);
    $to_prop->setAccessible(true);
    $from_prop->setValue($data_range, 2);
    $to_prop->setValue($data_range, 8);
    $ref->setValue($entity, $data_range);

    $builder_range = new ResponseBuilder($request_range);
    $entity->setDataIntoResponsePublic($builder_range, collect([['id' => 1]]), 1);

    expect($builder_range->getFrom())->toBe(2)
        ->and($builder_range->getTo())->toBe(8);
});
