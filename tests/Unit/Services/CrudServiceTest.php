<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\CrudService;
use Modules\Core\Services\Crud\QueryBuilder;
use Modules\Core\Tests\LaravelTestCase;
use Overtrue\LaravelVersionable\Versionable;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
use Approval\Traits\RequiresApproval;

uses(LaravelTestCase::class);

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

    $requestData = new class($model) extends \Modules\Core\Casts\CrudRequestData
    {
        public function __construct(Model $model)
        {
            $this->model = $model;
        }
    };

    $result = $service->clearModelCache($requestData);

    expect($result->data)->toBe('items cached cleared');
});

