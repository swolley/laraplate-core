<?php

declare(strict_types=1);

use Approval\Traits\RequiresApproval;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Casts\Column;
use Modules\Core\Casts\ColumnType;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\SelectRequestData;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\CrudService;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\Core\Tests\Fixtures\CrudServiceTestSingleRelChild;
use Modules\Core\Tests\Fixtures\CrudServiceTestSingleRelParent;
use Overtrue\LaravelVersionable\Versionable;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;


it('normalizes scalar and array key values to where condition', function (): void {
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $model = new class extends Model
    {
        protected $table = 'items';

        protected $primaryKey = 'id';
    };

    $ref = new ReflectionClass(CrudService::class);
    $method = $ref->getMethod('keyValueToWhereCondition');
    $method->setAccessible(true);

    $single = $method->invoke($service, $model, 10);
    $composite = $method->invoke($service, $model, ['tenant_id' => 1, 'id' => 10]);

    expect($single)->toBe(['id' => 10])
        ->and($composite)->toBe(['tenant_id' => 1, 'id' => 10]);
});

it('detects models using recursive, approval, and history traits', function (): void {
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $recursive = new class extends Model
    {
        use HasRecursiveRelationships;
    };

    $approvable = new class extends Model
    {
        use RequiresApproval;
    };

    $versioned = new class extends Model
    {
        use Versionable;
    };

    $ref = new ReflectionClass(CrudService::class);

    $useRecursive = $ref->getMethod('useRecursiveRelationships');
    $useRecursive->setAccessible(true);
    $useApproval = $ref->getMethod('useHasApproval');
    $useApproval->setAccessible(true);
    $hasHistory = $ref->getMethod('hasHistory');
    $hasHistory->setAccessible(true);

    expect($useRecursive->invoke($service, $recursive))->toBeTrue()
        ->and($useRecursive->invoke($service, $approvable))->toBeFalse()
        ->and($useApproval->invoke($service, $approvable))->toBeTrue()
        ->and($useApproval->invoke($service, $recursive))->toBeFalse()
        ->and($hasHistory->invoke($service, $versioned))->toBeTrue()
        ->and($hasHistory->invoke($service, $recursive))->toBeFalse();
});

it('clearModelCache clears cache for the given model and returns message', function (): void {
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $model = new class extends Model
    {
        protected $table = 'items';
    };

    Cache::shouldReceive('clearByEntity')
        ->once()
        ->with($model);

    $requestData = new class($model) extends Modules\Core\Casts\CrudRequestData
    {
        public function __construct(Model $model)
        {
            $this->model = $model;
        }
    };

    $result = $service->clearModelCache($requestData);

    expect($result->data)->toBe('items cached cleared');
});

it('translates elastic filters for less/less-equals/between operators', function (): void {
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $ref = new ReflectionClass(CrudService::class);
    $method = $ref->getMethod('translateFilterToElasticsearch');
    $method->setAccessible(true);

    $less = $method->invoke($service, new Filter('users.id', 10, FilterOperator::LESS));
    $less_equals = $method->invoke($service, new Filter('users.id', 10, FilterOperator::LESS_EQUALS));
    $between = $method->invoke($service, new Filter('users.id', [5, 15], FilterOperator::BETWEEN));

    expect($less)->toBe(['lt' => ['users.id' => 10]])
        ->and($less_equals)->toBe(['lte' => ['users.id' => 10]])
        ->and($between)->toBe(['range' => ['users.id' => ['gte' => 5, 'lte' => 15]]]);
});

it('translates elastic filters for remaining operators and nested groups', function (): void {
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $ref = new ReflectionClass(CrudService::class);
    $translate_filter = $ref->getMethod('translateFilterToElasticsearch');
    $translate_filter->setAccessible(true);
    $translate_group = $ref->getMethod('translateFiltersToElasticsearch');
    $translate_group->setAccessible(true);

    expect($translate_filter->invoke($service, new Filter('a', 1, FilterOperator::EQUALS)))
        ->toBe(['term' => ['a' => 1]]);
    expect($translate_filter->invoke($service, new Filter('a', 1, FilterOperator::NOT_EQUALS)))
        ->toBe(['bool' => ['must_not' => ['term' => ['a' => 1]]]]);
    expect($translate_filter->invoke($service, new Filter('a', 'x', FilterOperator::LIKE)))
        ->toBe(['wildcard' => ['a' => '*x*']]);
    expect($translate_filter->invoke($service, new Filter('a', 'x', FilterOperator::NOT_LIKE)))
        ->toBe(['bool' => ['must_not' => ['wildcard' => ['a' => '*x*']]]]);
    expect($translate_filter->invoke($service, new Filter('a', [1, 2], FilterOperator::IN)))
        ->toBe(['terms' => ['a' => [1, 2]]]);
    expect($translate_filter->invoke($service, new Filter('a', 5, FilterOperator::GREAT)))
        ->toBe(['gt' => ['a' => 5]]);
    expect($translate_filter->invoke($service, new Filter('a', 5, FilterOperator::GREAT_EQUALS)))
        ->toBe(['gte' => ['a' => 5]]);

    $nested_or = new FiltersGroup(
        filters: [new Filter('x', 1, FilterOperator::EQUALS)],
        operator: WhereClause::OR,
    );
    $root = new FiltersGroup(
        filters: [
            new Filter('root', 'v', FilterOperator::EQUALS),
            $nested_or,
        ],
        operator: WhereClause::AND,
    );

    $group_result = $translate_group->invoke($service, $root);

    expect($group_result)->toHaveCount(2);
    expect($group_result[1])->toHaveKey('bool');
    expect($group_result[1]['bool']['should'])->toBeArray();
});

it('extracts method columns grouped by relation and without duplicates', function (): void {
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $request_data = (new ReflectionClass(SelectRequestData::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($request_data, 'model'))->setValue($request_data, new Modules\Core\Models\User());
    (new ReflectionProperty($request_data, 'columns'))->setValue($request_data, [
        new Column('users.full_name', ColumnType::METHOD),
        new Column('users.roles.isEditable', ColumnType::METHOD),
        new Column('users.roles.isEditable', ColumnType::METHOD),
    ]);

    $ref = new ReflectionClass(CrudService::class);
    $method = $ref->getMethod('extractMethodColumns');
    $method->setAccessible(true);

    $methods_by_relation = $method->invoke($service, $request_data);

    expect($methods_by_relation)->toBe([
        '' => ['full_name'],
        'roles' => ['isEditable'],
    ]);
});

it('applies method columns to relation path targets', function (): void {
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $child_a = new class extends Model
    {
        public function exposeCode(): string
        {
            return 'A';
        }
    };
    $child_b = new class extends Model
    {
        public function exposeCode(): string
        {
            return 'B';
        }
    };

    $parent = new class extends Model
    {
        public function children(): mixed
        {
            return null;
        }
    };
    $parent->setRelation('children', new Collection([$child_a, $child_b]));

    $ref = new ReflectionClass(CrudService::class);
    $method = $ref->getMethod('applyMethodsToModel');
    $method->setAccessible(true);
    $method->invoke($service, $parent, ['children' => ['exposeCode']]);

    expect($child_a->getAttribute('exposeCode'))->toBe('A');
    expect($child_b->getAttribute('exposeCode'))->toBe('B');
});

it('returns early from relation path when nested segment is missing', function (): void {
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $mid = new class extends Model {};

    $parent = new class extends Model {};
    $parent->setRelation('mid', $mid);

    $ref = new ReflectionClass(CrudService::class);
    $method = $ref->getMethod('applyMethodsToRelationPath');
    $method->setAccessible(true);
    $method->invoke($service, $parent, 'mid.child', ['anything']);

    expect($mid->getAttribute('anything'))->toBeNull();
});

it('applies method columns when relation resolves to a single model', function (): void {
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $child = new CrudServiceTestSingleRelChild;
    $parent = new CrudServiceTestSingleRelParent;
    $parent->setRelation('childRecord', $child);

    $ref = new ReflectionClass(CrudService::class);
    $method = $ref->getMethod('applyMethodsToModel');
    $method->setAccessible(true);
    $method->invoke($service, $parent, ['childRecord' => ['childLabel']]);

    expect($child->getAttribute('childLabel'))->toBe('single');
});

it('applyComputedMethods iterates collection models when method columns requested', function (): void {
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $row = new class extends Model
    {
        protected $table = 'crud_cov_method_rows';

        public function rowLabel(): string
        {
            return 'L';
        }
    };

    $request_data = (new ReflectionClass(SelectRequestData::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($request_data, 'model'))->setValue($request_data, $row);
    (new ReflectionProperty($request_data, 'columns'))->setValue($request_data, [
        new Column('crud_cov_method_rows.rowLabel', ColumnType::METHOD),
    ]);

    $ref = new ReflectionClass(CrudService::class);
    $method = $ref->getMethod('applyComputedMethods');
    $method->setAccessible(true);
    $method->invoke($service, new Collection([$row]), $request_data);

    expect($row->getAttribute('rowLabel'))->toBe('L');
});

it('throws when resolveMethodValue targets missing or invalid methods', function (): void {
    $service = new CrudService(app(AuthorizationService::class), app(QueryBuilder::class));

    $model = new class extends Model
    {
        public function requiresArgument(string $value): string
        {
            return $value;
        }
    };

    $ref = new ReflectionClass(CrudService::class);
    $method = $ref->getMethod('resolveMethodValue');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($service, $model, 'missingMethod'))
        ->toThrow(UnexpectedValueException::class);
    expect(fn () => $method->invoke($service, $model, 'requiresArgument'))
        ->toThrow(UnexpectedValueException::class);
});
