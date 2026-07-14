<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud;

use Approval\Traits\RequiresApproval;
use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes as EloquentSoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Modules\Core\Cache\Repository as CacheRepository;
use Modules\Core\Casts\CrudRequestData;
use Modules\Core\Casts\DetailRequestData;
use Modules\Core\Casts\HistoryRequestData;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\ModifyRequestData;
use Modules\Core\Casts\SearchMode;
use Modules\Core\Casts\SearchRequestData;
use Modules\Core\Casts\TreeRequestData;
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\Core\Exceptions\CrudWriteNotAllowedException;
use Modules\Core\Locking\Exceptions\AlreadyLockedException;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;
use Modules\Core\Overrides\CustomSoftDeletingScope;
use Modules\Core\Search\DTOs\AdvancedSearchResult;
use Modules\Core\Search\Services\AdvancedSearchService;
use Modules\Core\Search\Services\ScoutSearchConstraintApplier;
use Modules\Core\Search\Traits\Searchable;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\Concerns\HasCrudOperations;
use Modules\Core\Services\Crud\DTOs\CrudMeta;
use Modules\Core\Services\Crud\DTOs\CrudResult;
use Modules\Core\SoftDeletes\SoftDeletes as CoreSoftDeletes;
use Overtrue\LaravelVersionable\Versionable;
use ReflectionMethod;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * CRUD Service - orchestrates CRUD operations with authorization and query building.
 *
 * This service uses:
 * - AuthorizationService: for permission checks and ACL filter injection
 * - QueryBuilder: for preparing Eloquent queries from request data
 *
 * The flow for read operations (list, detail, history, tree):
 * 1. ensurePermission() - verify user has permission for operation
 * 2. injectAclFilters() - inject ACL filters into request data
 * 3. prepareQuery() - build the query (filters now include ACLs)
 * 4. Execute query and return result
 */
class CrudService
{
    /** @phpstan-use HasCrudOperations<\Illuminate\Database\Eloquent\Model> */
    use HasCrudOperations;

    public function __construct(
        private readonly AuthorizationService $auth,
        private readonly QueryBuilder $query_builder,
        private readonly ?AdvancedSearchService $advanced_search = null,
        private readonly ?ScoutSearchConstraintApplier $search_constraint_applier = null,
    ) {}

    public function list(ListRequestData $requestData): CrudResult
    {
        $model = $requestData->model;

        // 1. Check permission
        $permission_name = $this->auth->ensurePermission(
            $requestData->request,
            $model->getTable(),
            'select',
            $model->getConnectionName(),
        );

        // 2. Inject ACL filters into request (filters become: ACL AND user_filters)
        $this->auth->injectAclFilters($requestData, $permission_name);

        // 3. Build query (now includes ACL filters)
        $query = $model->newQuery();
        $this->query_builder->prepareQuery($query, $requestData);

        $total_records = $query->count();

        $data = match (true) {
            $requestData->page !== null => $this->listByPagination($query, $requestData, $total_records),
            $requestData->from !== null => $this->listByFromTo($query, $requestData, $total_records),
            default => $this->listByOthers($query, $requestData, $total_records),
        };

        $this->applyComputedMethods($data, $requestData);

        if ($requestData->group_by !== [] && $data instanceof Collection) {
            $data = $this->applyGroupBy($data, $requestData->group_by);
        }

        $current_records = is_numeric($data) ? $data : $data->count();

        $meta = new CrudMeta(
            totalRecords: $total_records,
            currentRecords: $current_records,
            currentPage: $requestData->page,
            totalPages: $requestData->page !== null ? $requestData->calculateTotalPages($total_records) : null,
            pagination: $requestData->pagination,
            from: $requestData->from,
            to: $requestData->to,
            class: $model::class,
            table: $model->getTable(),
            cachedAt: Date::now(),
        );

        return new CrudResult(
            data: $data,
            meta: $meta,
        );
    }

    public function detail(DetailRequestData $requestData): CrudResult
    {
        $model = $requestData->model;

        // 1. Check permission
        $permission_name = $this->auth->ensurePermission(
            $requestData->request,
            $model->getTable(),
            'select',
            $model->getConnectionName(),
        );

        // 2. Constrain by primary key first (from validated/input/route so record-not-found can 404)
        $key = $this->getModelPrimaryKeyName($model);

        if (is_array($key)) {
            $key_value = array_map(
                fn (string $k): mixed => $this->resolveKeyFromRequest($requestData->request, $k),
                $key,
            );
            throw_if(
                array_any($key_value, static fn (mixed $value): bool => $value === null || $value === ''),
                ModelNotFoundException::class,
                'Primary key is required for detail.',
            );
            $query = $model->newQuery()->where(array_combine($key, $key_value));
        } else {
            $key_value = $this->resolveKeyFromRequest($requestData->request, $key);
            throw_if($key_value === null || $key_value === '', ModelNotFoundException::class, 'Primary key is required for detail.');
            $query = $model->newQuery()->where([$key => $key_value]);
        }

        // 3. Build query and apply ACL filters
        $this->auth->applyAclFiltersToQuery($query, $permission_name);
        $this->query_builder->prepareQuery($query, $requestData);

        $data = $query->sole();

        $this->applyComputedMethods($data, $requestData);

        $meta = new CrudMeta(
            class: $model::class,
            table: $model->getTable(),
            cachedAt: Date::now(),
        );

        return new CrudResult(
            data: $data,
            meta: $meta,
        );
    }

    public function search(SearchRequestData $requestData): CrudResult
    {
        $model = $requestData->model;

        $is_searchable_class = class_uses_trait($model, Searchable::class);

        if (! $is_searchable_class) {
            return new CrudResult(
                data: null,
                error: 'Full-search operation can be done only on Searchable entities',
                statusCode: Response::HTTP_BAD_REQUEST,
            );
        }

        // 2. Check permission
        $permission_name = $this->auth->ensurePermission(
            $requestData->request,
            $model->getTable(),
            'select',
            $model->getConnectionName(),
        );

        return match ($requestData->mode) {
            SearchMode::Orchestrated => $this->searchWithAdvanced($requestData, $permission_name),
            SearchMode::Auto => ($this->advanced_search ?? app(AdvancedSearchService::class))->available($model)
                ? $this->searchWithAdvanced($requestData, $permission_name)
                : $this->searchWithScout($requestData, $permission_name),
            SearchMode::Basic => $this->searchWithScout($requestData, $permission_name),
        };
    }

    public function history(HistoryRequestData $requestData): CrudResult
    {
        $model = $requestData->model;

        throw_unless($this->hasHistory($model), BadMethodCallException::class, sprintf("'%s' doesn't have history handling", $requestData->mainEntity));

        // 1. Check permission
        $permission_name = $this->auth->ensurePermission(
            $requestData->request,
            $model->getTable(),
            'select',
            $model->getConnectionName(),
        );

        // 2. Build query and apply ACL filters
        $query = $model->newQuery();
        $this->auth->applyAclFiltersToQuery($query, $permission_name);
        $this->query_builder->prepareQuery($query, $requestData);

        $query->with('history', function (Relation $q) use ($requestData): void {
            $q->latest();

            if (isset($requestData->limit)) {
                $q->take($requestData->limit);
            }
        });

        if (! preview() && $this->useHasApproval($model)) {
            $query->with('modifications');
        }

        $data = $query->sole();

        $this->applyComputedMethods($data, $requestData);

        $history_relation = $data->getRelation('history');
        $history_array = [];

        if ($history_relation !== null && $history_relation instanceof Collection) {
            $history_array = $history_relation->toArray();
        }

        $meta = new CrudMeta(
            class: $model::class,
            table: $model->getTable(),
            cachedAt: Date::now(),
        );

        $record_array = $data->getAttributes();

        $payload = [
            'record' => $record_array,
            'history' => $history_array,
        ];

        return new CrudResult(
            data: $payload,
            meta: $meta,
        );
    }

    public function tree(TreeRequestData $requestData): CrudResult
    {
        $model = $requestData->model;

        throw_unless($this->useRecursiveRelationships($model), UnexpectedValueException::class, sprintf("'%s' is not a hierarchical class", $requestData->mainEntity));

        // 1. Check permission
        $permission_name = $this->auth->ensurePermission(
            $requestData->request,
            $model->getTable(),
            'select',
            $model->getConnectionName(),
        );

        $tree_relation_type = [];

        if ($requestData->parents && $requestData->children) {
            $tree_relation_type = 'bloodline';
        } elseif ($requestData->parents) {
            $tree_relation_type = 'ancestorsAndSelf';
        } elseif ($requestData->children) {
            $tree_relation_type = 'descendantsAndSelf';
        }

        // 2. Build query and apply ACL filters
        $query = $model->newQuery()->with($tree_relation_type);
        $this->auth->applyAclFiltersToQuery($query, $permission_name);
        $this->query_builder->prepareQuery($query, $requestData);

        $data = $requestData->request->has(is_array($requestData->primaryKey) ? $requestData->primaryKey[0] : $requestData->primaryKey)
            ? $query->sole()
            : $query->get();

        $this->applyComputedMethods($data, $requestData);

        $meta = new CrudMeta(
            class: $model::class,
            table: $model->getTable(),
            cachedAt: Date::now(),
        );

        return new CrudResult(
            data: $data,
            meta: $meta,
        );
    }

    public function insert(ModifyRequestData $requestData): CrudResult
    {
        $model = $requestData->model;
        $this->assertCrudWriteAllowed($model, 'insert');
        $this->auth->ensurePermission($requestData->request, $model->getTable(), 'insert', $model->getConnectionName());
        $changes = $requestData->changes;
        $discarded_values = $this->removeNonFillableProperties($model, $changes);

        $created = $model->create($changes);

        throw_unless($created, LogicException::class, 'Record not created');

        $created->fresh();

        $error = $discarded_values === [] ? null : implode(', ', $discarded_values);

        return new CrudResult(
            data: $created,
            error: $error,
            statusCode: Response::HTTP_CREATED,
        );
    }

    public function update(ModifyRequestData $requestData): CrudResult
    {
        $model = $requestData->model;
        $this->assertCrudWriteAllowed($model, 'update');
        $this->auth->ensurePermission($requestData->request, $model->getTable(), 'update', $model->getConnectionName());

        $key_value = $this->getModelKeyValue($requestData);
        $found_records = $model->newQuery()->where($this->keyValueToWhereCondition($model, $key_value))->lazy(100);
        $changes = $requestData->changes;
        $discarded_values = $this->removeNonFillableProperties($model, $changes);

        throw_if($found_records->isEmpty() && $requestData->request->has('id'), ModelNotFoundException::class, 'No model Found');
        $updated_records = new Collection();
        DB::transaction(function () use ($found_records, $updated_records, $changes): void {
            foreach ($found_records as $found_record) {
                /** @psalm-suppress InvalidArgument */
                if ($found_record->update($changes)) {
                    $updated_records->add($found_record->fresh());
                }
            }
        });

        $error = $this->filterExpectedDiscardedForError($discarded_values, $requestData);

        return new CrudResult(
            data: $updated_records,
            error: $error,
        );
    }

    public function delete(ModifyRequestData $requestData): CrudResult
    {
        $model = $requestData->model;
        $this->assertCrudWriteAllowed($model, 'delete');
        $this->auth->ensurePermission($requestData->request, $model->getTable(), 'forceDelete', $model->getConnectionName());
        $key_value = $this->getModelKeyValue($requestData);
        $found_records = $model->newQuery()->where($this->keyValueToWhereCondition($model, $key_value))->lazy(100);

        throw_if($found_records->isEmpty() && $requestData->request->has('id'), ModelNotFoundException::class, 'No model Found');
        $deleted_count = 0;
        DB::transaction(function () use ($found_records, &$deleted_count): void {
            foreach ($found_records as $found_record) {
                if ($found_record->forceDelete()) {
                    $deleted_count++;
                }
            }
        });

        return new CrudResult(
            data: ['deleted' => $deleted_count],
            statusCode: Response::HTTP_OK,
        );
    }

    public function doActivateOperation(ModifyRequestData $requestData, string $operation): CrudResult
    {
        $model = $requestData->model;
        $this->assertCrudWriteAllowed($model, $operation === 'activate' ? 'restore' : 'delete');
        $this->auth->ensurePermission($requestData->request, $model->getTable(), 'restore', $model->getConnectionName());
        $key_value = $this->getModelKeyValue($requestData);
        $found_record = $this->newQueryWithTrashed($model)
            ->where($this->keyValueToWhereCondition($model, $key_value))
            ->firstOrFail();

        throw_if($operation === 'activate' && (! method_exists($found_record, 'restore') || ! $found_record->restore()), LogicException::class, 'Record not activated');

        throw_unless($found_record->delete(), LogicException::class, 'Record not inactivated');

        $found_record->fresh();

        return new CrudResult(
            data: $found_record,
        );
    }

    public function activate(ModifyRequestData $requestData): CrudResult
    {
        return $this->doActivateOperation($requestData, 'activate');
    }

    public function inactivate(ModifyRequestData $requestData): CrudResult
    {
        return $this->doActivateOperation($requestData, 'inactivate');
    }

    public function approve(ModifyRequestData $requestData): CrudResult
    {
        return $this->doApproveOperation($requestData, 'approve');
    }

    public function disapprove(ModifyRequestData $requestData): CrudResult
    {
        return $this->doApproveOperation($requestData, 'disapprove');
    }

    public function lock(ModifyRequestData $requestData): CrudResult
    {
        return $this->doLockOperation($requestData, 'lock');
    }

    public function unlock(ModifyRequestData $requestData): CrudResult
    {
        return $this->doLockOperation($requestData, 'unlock');
    }

    public function clearModelCache(CrudRequestData $requestData): CrudResult
    {
        $model = $requestData->model;
        $table = $model->getTable();
        $cache = Cache::store();

        if ($cache instanceof CacheRepository) {
            $cache->clearByEntity($model);
        }

        return new CrudResult(
            data: $table . ' cached cleared',
            statusCode: Response::HTTP_OK,
        );
    }

    private function assertCrudWriteAllowed(Model $model, string $operation): void
    {
        if ($model instanceof RestrictsCrudWrites && in_array($operation, $model->deniedCrudWrites(), true)) {
            throw CrudWriteNotAllowedException::for($model, $operation);
        }
    }

    private function useRecursiveRelationships(Model $model): bool
    {
        return class_uses_trait($model, HasRecursiveRelationships::class);
    }

    private function useHasApproval(Model $model): bool
    {
        return class_uses_trait($model, RequiresApproval::class);
    }

    private function hasHistory(Model $model): bool
    {
        return class_uses_trait($model, Versionable::class);
    }

    /**
     * @return array<string, mixed>|string|int
     */
    private function getModelKeyValue(ModifyRequestData $filters): array|string|int
    {
        /** @var string|array<int,string> $key */
        $key = $this->getModelPrimaryKeyName($filters->model);

        if (is_string($key)) {
            return $filters->{$key};
        }

        $key_value = array_flip($key);

        foreach ($key as $k) {
            $key_value[$k] = $filters->{$k};
        }

        return $key_value;
    }

    /**
     * Normalize key value to an array suitable for Builder::where() (column => value or [col => val, ...]).
     *
     * @param  array<string, mixed>|string|int  $keyValue
     * @return array<string, mixed>
     */
    private function keyValueToWhereCondition(Model $model, array|string|int $keyValue): array
    {
        return is_array($keyValue) ? $keyValue : [$model->getKeyName() => $keyValue];
    }

    /**
     * Filter discarded-value messages so that request-metadata keys (filters, primary key) are not reported as errors.
     *
     * @param  array<int, string>  $discardedMessages
     */
    private function filterExpectedDiscardedForError(array $discardedMessages, ModifyRequestData $requestData): ?string
    {
        $pk_keys = is_array($requestData->primaryKey) ? $requestData->primaryKey : [$requestData->primaryKey];
        $expected = array_merge(['filters'], $pk_keys);
        $unexpected = array_filter($discardedMessages, static fn (string $msg): bool => array_all($expected, fn (string|int $key): bool => ! str_contains($msg, "'{$key}'")));

        return $unexpected === [] ? null : implode(', ', $unexpected);
    }

    private function searchWithScout(SearchRequestData $requestData, string $permissionName): CrudResult
    {
        $model = $requestData->model;

        if (is_array($model->getKeyName())) {
            return new CrudResult(
                data: null,
                error: 'Full-search operation does not support composite primary keys yet.',
                statusCode: Response::HTTP_BAD_REQUEST,
            );
        }

        /** @var class-string<Model> $model_class */
        $model_class = $model::class;
        $builder = $model_class::search($requestData->qs);

        $this->auth->injectAclFilters($requestData, $permissionName);

        try {
            $this->searchConstraintApplier()->apply($builder, $model, $requestData->filters, $requestData->sort);
        } catch (InvalidArgumentException $exception) {
            return $this->invalidSearchConstraintsResult($exception->getMessage());
        }

        if ($requestData->page !== null) {
            $paginator = $builder->paginate($requestData->pagination, 'page', $requestData->page);
            $search_results = $paginator->getCollection();
            $total_records = $paginator->total();
        } else {
            $search_results = $builder->take($requestData->limit)->get();
            $total_records = $search_results->count();
        }

        $ids = $search_results
            ->map(static fn (Model $record): mixed => $record->getKey())
            ->filter(static fn (mixed $key): bool => $key !== null && $key !== '')
            ->values();

        if ($ids->isEmpty()) {
            return new CrudResult(
                data: new Collection(),
                meta: $this->buildSearchMeta($requestData, 0, 0),
            );
        }

        $query = $model->newQuery()->whereKey($ids->all());

        if ($requestData->relations !== []) {
            $query->with($requestData->relations);
        }

        $records = $query->get();

        $records_by_key = $records->keyBy(static fn (Model $record): string => (string) $record->getKey());
        $records = new Collection(
            $ids
                ->map(static fn (mixed $id): ?Model => $records_by_key->get((string) $id))
                ->filter()
                ->values()
                ->all(),
        );

        $this->applyComputedMethods($records, $requestData);

        $data = $requestData->group_by !== []
            ? $this->applyGroupBy($records, $requestData->group_by)
            : $records;

        $current_records = $data->count();

        return new CrudResult(
            data: $data,
            meta: $this->buildSearchMeta($requestData, $total_records, $current_records),
        );
    }

    private function searchWithAdvanced(SearchRequestData $requestData, string $permissionName): CrudResult
    {
        $advanced_search = $this->advanced_search ?? app(AdvancedSearchService::class);

        if (! $advanced_search->available($requestData->model)) {
            return new CrudResult(
                data: null,
                error: 'Orchestrated search pipeline is not available for the configured search driver.',
                statusCode: Response::HTTP_NOT_IMPLEMENTED,
            );
        }

        if ($requestData->group_by !== []) {
            return $this->invalidSearchConstraintsResult('Orchestrated search does not support CRUD group_by yet.');
        }

        $this->auth->injectAclFilters($requestData, $permissionName);

        try {
            $result = $advanced_search->search(
                $requestData->model,
                $requestData->qs,
                $requestData->page ?? 1,
                max(1, $requestData->page !== null ? $requestData->pagination : $requestData->limit),
                $requestData->filters,
                $requestData->sort,
                $requestData->matching,
                $requestData->matching_options,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->invalidSearchConstraintsResult($exception->getMessage());
        }

        return $this->searchResultFromAdvancedResult($requestData, $result);
    }

    private function searchResultFromAdvancedResult(SearchRequestData $requestData, AdvancedSearchResult $result): CrudResult
    {
        $model = $requestData->model;
        $ids = $result->ids();

        if ($ids === []) {
            return new CrudResult(
                data: new Collection(),
                meta: $this->buildAdvancedSearchMeta($requestData, $result, 0),
            );
        }

        $query = $model->newQuery()->whereKey($ids);

        if ($requestData->relations !== []) {
            $query->with($requestData->relations);
        }

        $records = $query->get();
        $records_by_key = $records->keyBy(static fn (Model $record): string => (string) $record->getKey());
        $data = new Collection(
            collect($ids)
                ->map(static fn (string $id): ?Model => $records_by_key->get($id))
                ->filter()
                ->values()
                ->all(),
        );

        $this->applyComputedMethods($data, $requestData);

        return new CrudResult(
            data: $data,
            meta: $this->buildAdvancedSearchMeta($requestData, $result, $data->count()),
        );
    }

    private function searchConstraintApplier(): ScoutSearchConstraintApplier
    {
        return $this->search_constraint_applier ?? app(ScoutSearchConstraintApplier::class);
    }

    private function invalidSearchConstraintsResult(string $message): CrudResult
    {
        return new CrudResult(
            data: null,
            error: $message,
            statusCode: Response::HTTP_BAD_REQUEST,
        );
    }

    private function buildSearchMeta(SearchRequestData $requestData, int $totalRecords, int $currentRecords): CrudMeta
    {
        $model = $requestData->model;

        return new CrudMeta(
            totalRecords: $totalRecords,
            currentRecords: $currentRecords,
            currentPage: $requestData->page,
            totalPages: $requestData->page !== null ? $requestData->calculateTotalPages($totalRecords) : null,
            pagination: $requestData->pagination,
            from: $requestData->from,
            to: $requestData->to,
            class: $model::class,
            table: $model->getTable(),
            cachedAt: Date::now(),
        );
    }

    private function buildAdvancedSearchMeta(SearchRequestData $requestData, AdvancedSearchResult $result, int $currentRecords): CrudMeta
    {
        $model = $requestData->model;

        return new CrudMeta(
            totalRecords: $result->total,
            currentRecords: $currentRecords,
            currentPage: $result->page,
            totalPages: $result->totalPages,
            pagination: $result->perPage,
            from: $requestData->from,
            to: $requestData->to,
            class: $model::class,
            table: $model->getTable(),
            cachedAt: Date::now(),
            search: $result->meta,
        );
    }

    private function doApproveOperation(ModifyRequestData $requestData, string $operation): CrudResult
    {
        $model = $requestData->model;
        $this->assertCrudWriteAllowed($model, $operation);
        $this->auth->ensurePermission($requestData->request, $model->getTable(), 'approve', $model->getConnectionName());

        $key_value = $this->getModelKeyValue($requestData);
        $found_record = $this->newQueryWithTrashed($model)
            ->where($this->keyValueToWhereCondition($model, $key_value))
            ->firstOrFail();

        $user = Auth::user();
        throw_unless($user instanceof User, LogicException::class, 'Authenticated user is required.');

        if (isset($requestData->changes['modification'])) {
            $modification = Modification::query()
                ->where('modifiable_type', $model::class)
                ->where('modifiable_id', $requestData->primaryKey)
                ->whereKey($requestData->changes['modification'])
                ->sole();

            $reason = $requestData->changes['reason'] ?? null;
            $vote_reason = is_string($reason) ? $reason : null;

            if ($operation === 'approve') {
                $user->approve($modification, $vote_reason);
            } else {
                $user->disapprove($modification, $vote_reason);
            }
        } else {
            $modifications = Modification::query()
                ->where('modifiable_type', $found_record::class)
                ->where('modifiable_id', $found_record->getKey())
                ->activeOnly()
                ->oldest()
                ->cursor();

            throw_if($modifications->isEmpty(), LogicException::class, sprintf('No modifications to be %sd', $operation));

            $reason = $requestData->changes['reason'] ?? null;
            $vote_reason = is_string($reason) ? $reason : null;

            foreach ($modifications as $modification) {
                if ($operation === 'approve') {
                    $user->approve($modification, $vote_reason);
                } else {
                    $user->disapprove($modification, $vote_reason);
                }
            }
        }

        $found_record->refresh();

        return new CrudResult(
            data: $found_record,
        );
    }

    private function doLockOperation(ModifyRequestData $requestData, string $operation): CrudResult
    {
        $model = $requestData->model;
        $this->assertCrudWriteAllowed($model, $operation);

        throw_unless(class_uses_trait($model, HasLocks::class), BadMethodCallException::class, $model::class . " doesn't support locks");
        $this->auth->ensurePermission($requestData->request, $model->getTable(), 'lock', $model->getConnectionName());
        $key_value = $this->getModelKeyValue($requestData);

        $found_records = $model->newQuery()->where($this->keyValueToWhereCondition($model, $key_value))->lazy(100);

        throw_if($found_records->isEmpty() && $requestData->request->has('id'), ModelNotFoundException::class, 'No model Found');

        $first_record = $found_records->first();
        throw_if($first_record === null, ModelNotFoundException::class, 'No model Found');

        $can_be_done = ($operation === 'lock' && $this->recordIsLocked($first_record)) || ! $this->recordIsLocked($first_record);

        throw_if($found_records->count() === 1 && $requestData->request->has('id') && $can_be_done, AlreadyLockedException::class, $operation === 'lock' ? 'Record already locked' : "Record isn't locked");
        $locked_records = new Collection();
        DB::transaction(function () use ($found_records, $locked_records): void {
            foreach ($found_records as $found_record) {
                if (! $this->recordIsLocked($found_record) && method_exists($found_record, 'lock')) {
                    $found_record->lock();
                    $locked_records->add($found_record->fresh());
                }
            }
        });

        return new CrudResult(
            data: $locked_records,
        );
    }

    private function applyComputedMethods(mixed $data, ListRequestData|\Modules\Core\Casts\SelectRequestData $request_data): void
    {
        $methods_by_relation = $this->extractMethodColumns($request_data);

        if ($methods_by_relation === []) {
            return;
        }

        if ($data instanceof Model) {
            $this->applyMethodsToModel($data, $methods_by_relation);

            return;
        }

        if (is_iterable($data)) {
            foreach ($data as $model) {
                if ($model instanceof Model) {
                    $this->applyMethodsToModel($model, $methods_by_relation);
                }
            }
        }
    }

    /**
     * @return array<string,array<int,string>>
     */
    private function extractMethodColumns(\Modules\Core\Casts\SelectRequestData $request_data): array
    {
        $methods_by_relation = [];
        $main_entity = $request_data->model->getTable();

        foreach ($request_data->columns ?? [] as $column) {
            if ($column->type !== \Modules\Core\Casts\ColumnType::Method) {
                continue;
            }

            $index = str_replace($main_entity . '.', '', $column->name);
            $splitted = preg_split('/\.(?=[^.]*$)/', $index, 2);

            if ($splitted === false) {
                continue;
            }

            $relation = $splitted[1] ?? null ? $splitted[0] : '';
            $method = $splitted[1] ?? $splitted[0];

            if (! array_key_exists($relation, $methods_by_relation)) {
                $methods_by_relation[$relation] = [];
            }

            if (! in_array($method, $methods_by_relation[$relation], true)) {
                $methods_by_relation[$relation][] = $method;
            }
        }

        return $methods_by_relation;
    }

    /**
     * @param  array<string,array<int,string>>  $methods_by_relation
     */
    private function applyMethodsToModel(Model $model, array $methods_by_relation): void
    {
        foreach ($methods_by_relation as $relation_path => $methods) {
            if ($relation_path === '') {
                $this->applyMethodsToTarget($model, $methods);

                continue;
            }

            $this->applyMethodsToRelationPath($model, $relation_path, $methods);
        }
    }

    /**
     * @param  array<int,string>  $methods
     */
    private function applyMethodsToTarget(Model $model, array $methods): void
    {
        foreach ($methods as $method) {
            $value = $this->resolveMethodValue($model, $method);
            $model->setAttribute($method, $value);
        }
    }

    /**
     * @param  array<int,string>  $methods
     */
    private function applyMethodsToRelationPath(Model $model, string $relation_path, array $methods): void
    {
        $segments = explode('.', $relation_path);
        $targets = [$model];

        foreach ($segments as $segment) {
            $next_targets = [];

            foreach ($targets as $target) {
                if (! method_exists($target, $segment)) {
                    continue;
                }

                $related = $target->{$segment};

                if ($related instanceof Model) {
                    $next_targets[] = $related;

                    continue;
                }

                if (is_iterable($related)) {
                    foreach ($related as $item) {
                        if ($item instanceof Model) {
                            $next_targets[] = $item;
                        }
                    }
                }
            }

            if ($next_targets === []) {
                return;
            }

            $targets = $next_targets;
        }

        foreach ($targets as $target) {
            $this->applyMethodsToTarget($target, $methods);
        }
    }

    private function resolveMethodValue(Model $model, string $method): mixed
    {
        throw_unless(method_exists($model, $method), UnexpectedValueException::class, sprintf('Method %s not found on %s', $method, $model::class));

        $reflected_method = new ReflectionMethod($model, $method);
        throw_if($reflected_method->getNumberOfRequiredParameters() > 0, UnexpectedValueException::class, sprintf('Method %s requires parameters on %s', $method, $model::class));

        return $model->{$method}();
    }

    /**
     * @return string|array<int, string>
     */
    private function getModelPrimaryKeyName(Model $model): array|string
    {
        /** @var string|array<int, string> $key */
        return $model->getKeyName();
    }

    private function resolveKeyFromRequest(Request $request, string $key): mixed
    {
        if ($request instanceof FormRequest) {
            $validated = $request->validated($key);

            if ($validated !== null && $validated !== '') {
                return $validated;
            }
        }

        return $request->input($key) ?? $request->route($key);
    }

    /**
     * @return Builder<Model>
     */
    private function newQueryWithTrashed(Model $model): Builder
    {
        $query = $model->newQuery();
        $traits = class_uses_recursive($model::class);

        if (in_array(CoreSoftDeletes::class, $traits, true)) {
            $query->withoutGlobalScope(CustomSoftDeletingScope::class);

            return $query;
        }

        if (in_array(EloquentSoftDeletes::class, $traits, true)) {
            $query->withoutGlobalScope(SoftDeletingScope::class);
        }

        return $query;
    }

    private function recordIsLocked(Model $model): bool
    {
        $locked_at_column = (new \Modules\Core\Locking\Locked())->lockedAtColumn();

        return $model->getAttribute($locked_at_column) !== null;
    }

}
