<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Helpers\HasCrudOperations;
use Modules\Core\Models\User;
use ReflectionMethod;

final class CrudOperationsHarness
{
    use HasCrudOperations;

    /**
     * @param  Builder<User>  $query
     * @return EloquentCollection<int, User>
     */
    public function exposeListByPagination(Builder $query, ListRequestData $filters, int $totalRecords): EloquentCollection
    {
        return $this->listByPagination($query, $filters, $totalRecords);
    }

    /**
     * @param  Builder<User>  $query
     * @return EloquentCollection<int, User>|int
     */
    public function exposeListByFromTo(Builder $query, ListRequestData $filters, int $totalRecords): EloquentCollection|int
    {
        return $this->listByFromTo($query, $filters, $totalRecords);
    }

    /**
     * @param  Builder<User>  $query
     * @return EloquentCollection<int, User>|int
     */
    public function exposeListByOthers(Builder $query, ListRequestData $filters, int $totalRecords): EloquentCollection|int
    {
        return $this->listByOthers($query, $filters, $totalRecords);
    }

    /**
     * @param  array<int, array{field: string, direction?: 'asc'|'desc'}>  $sorts
     */
    public function exposeApplySorting(Builder $query, array $sorts): void
    {
        $this->applySorting($query, $sorts);
    }

    public function exposeGetCacheKey(Model $model, array $params): string
    {
        return $this->getCacheKey($model, $params);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<int, string>
     */
    public function exposeRemoveNonFillableProperties(Model $model, array &$values): array
    {
        $m = new ReflectionMethod($this, 'removeNonFillableProperties');
        $m->setAccessible(true);

        /** @var array<int, string> */
        return $m->invokeArgs($this, [$model, &$values]);
    }

    /**
     * @param  EloquentCollection<int, object>  $data
     * @param  array<int, string>  $groupBy
     * @return EloquentCollection<string, EloquentCollection<int, object>>
     */
    public function exposeApplyGroupBy(EloquentCollection &$data, array $groupBy): EloquentCollection
    {
        $m = new ReflectionMethod($this, 'applyGroupBy');
        $m->setAccessible(true);

        return $m->invokeArgs($this, [&$data, $groupBy]);
    }

    public function exposeApplyFilter(Builder $query, string $field, array $filter): void
    {
        $m = new ReflectionMethod($this, 'applyFilter');
        $m->setAccessible(true);
        $m->invoke($this, $query, $field, $filter);
    }
}
