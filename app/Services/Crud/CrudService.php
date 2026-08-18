<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud;

use Approval\Traits\RequiresApproval;
use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes as EloquentSoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Fluent;
use InvalidArgumentException;
use LogicException;
use Modules\Core\Cache\Repository as CacheRepository;
use Modules\Core\Casts\CrudRequestData;
use Modules\Core\Casts\DetailRequestData;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\HistoryRequestData;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\ModifyRequestData;
use Modules\Core\Casts\SearchMode;
use Modules\Core\Casts\SearchRequestData;
use Modules\Core\Casts\TreeRequestData;
use Modules\Core\Contracts\ProvidesFacetLabelSources;
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\Core\Exceptions\CrudWriteNotAllowedException;
use Modules\Core\Helpers\LocaleContext;
use Modules\Core\Locking\Exceptions\AlreadyLockedException;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Models\Approval;
use Modules\Core\Models\Disapproval;
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
use Modules\Core\Services\Crud\DTOs\FacetLabelSource;
use Modules\Core\Services\Crud\DTOs\FacetPage;
use Modules\Core\Services\Crud\DTOs\FacetQuery;
use Modules\Core\Services\Crud\DTOs\FacetSort;
use Modules\Core\SoftDeletes\SoftDeletes as CoreSoftDeletes;
use Overtrue\LaravelVersionable\Versionable;
use ReflectionMethod;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
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

    /**
     * Memoized "method requires parameters" checks, keyed by `class::method`.
     *
     * The reflection result depends only on the class + method signature, so it
     * is cached to avoid rebuilding a ReflectionMethod for every row when a
     * computed Method column is resolved over a collection.
     *
     * @var array<string, bool>
     */
    private static array $method_requires_parameters_cache = [];

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

        // When the full result set is materialized (no page, no from/to range, no
        // limit cap and not a count-only request), the total equals the number of
        // fetched rows, so the separate COUNT(*) round-trip is redundant.
        $is_full_get = $requestData->page === null
            && $requestData->from === null
            && ! isset($requestData->limit)
            && ! $requestData->count;

        $total_records = $is_full_get ? 0 : $query->count();

        $data = match (true) {
            $requestData->page !== null => $this->listByPagination($query, $requestData, $total_records),
            $requestData->from !== null => $this->listByFromTo($query, $requestData, $total_records),
            default => $this->listByOthers($query, $requestData, $total_records),
        };

        if ($is_full_get && $data instanceof Collection) {
            $total_records = $data->count();
        }

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

    /**
     * Per-facet value counts. Every requested column is treated as a facet dimension.
     *
     * For each facet value, `total` is the value distribution ignoring the request
     * filters, while `count` applies them minus the facet's own selection (so a facet
     * stays live as its values are toggled), reusing the list filter machinery. Results
     * are keyed by the facet's bare column name.
     *
     * @return array<string, list<array{value: mixed, total: int, count: int}>>
     */
    public function facetCounts(ListRequestData $base): array
    {
        $model = $base->model;

        $permission_name = $this->auth->ensurePermission(
            $base->request,
            $model->getTable(),
            'select',
            $model->getConnectionName(),
        );

        $result = [];

        foreach ($base->columns as $column) {
            // Direct-column scope: the bare column name resolves against the model's
            // own table, regardless of any entity-qualified prefix on the request.
            $field = $this->facetKey($column->name);

            // Both universes stay within the ACL-visible rows: `total` ignores the
            // request filters but not the row-level ACL, `count` applies both.
            $total_query = $model->newQuery();
            $this->auth->applyAclFiltersToQuery($total_query, $permission_name);
            $totals = $total_query->toBase()->pluck($field)->countBy();

            $count_query = $model->newQuery();
            $this->auth->applyAclFiltersToQuery($count_query, $permission_name);

            if ($base->filters instanceof FiltersGroup) {
                $this->query_builder->applyFilters($count_query, $this->excludeFacetField($base->filters, $field));
            }

            $counts = $count_query->toBase()->pluck($field)->countBy();

            $result[$field] = $totals
                ->map(static fn (int $occurrences, mixed $value): array => [
                    'value' => $value,
                    'total' => $occurrences,
                    'count' => (int) ($counts[$value] ?? 0),
                ])
                ->values()
                ->all();
        }

        return $result;
    }

    /**
     * One open (high-cardinality) facet's value page: a SQL `GROUP BY` on the key
     * column, paginated, value-searchable and ordered by count or key, with the
     * double counter and the key≠label two-step.
     *
     * The heavy `total`/`count` counting stays a single grouped query on the
     * model's own connection (never an in-memory `group_by`, so it scales), and
     * display fields are resolved in a second bounded `whereIn` over just the
     * page's keys — so `GROUP BY` stays on one portable column and labelling is a
     * cheap round trip. `total` ignores the request filters (the value universe),
     * `count` applies them minus this facet's own selection (keeping it live), and
     * both stay within the ACL-visible rows.
     */
    public function facetValues(ListRequestData $base, FacetQuery $facet): FacetPage
    {
        $model = $base->model;

        $permission_name = $this->auth->ensurePermission(
            $base->request,
            $model->getTable(),
            'select',
            $model->getConnectionName(),
        );

        if ($facet->relation !== null) {
            return $this->facetRelationValues($base, $facet, $permission_name);
        }

        $to_one = $this->resolveToOneColumn($model, $facet->groupBy);

        if ($to_one !== null) {
            return $this->facetRelatedColumnValues($base, $facet, $permission_name, $to_one);
        }

        $key = $this->facetKey($facet->groupBy);

        $filtered = $model->newQuery();
        $this->auth->applyAclFiltersToQuery($filtered, $permission_name);

        if ($base->filters instanceof FiltersGroup) {
            $this->query_builder->applyFilters($filtered, $this->excludeFacetField($base->filters, $key));
        }

        $filtered_base = $filtered->toBase();

        // A relation label (single-hop BelongsTo keyed by the group key) lets the
        // facet search and sort by the label instead of the raw key — resolved
        // without a join, so no column collides with the ACL/soft-delete scopes.
        $label_relation = $facet->labelField === null
            ? null
            : $this->resolveLabelRelation($model, $key, $facet->labelField);

        if ($facet->search !== null) {
            if ($label_relation === null) {
                $filtered_base->where($key, 'like', '%' . $facet->search . '%');
            } elseif (isset($label_relation['translation'])) {
                // Translated label: the translation's foreign key is the group key.
                $translation = $label_relation['translation'];
                $matching_keys = $translation['model']->newQuery()
                    ->where($translation['column'], 'like', '%' . $facet->search . '%')
                    ->where('locale', $translation['locale'])
                    ->pluck($translation['foreign'])
                    ->all();

                $filtered_base->whereIn($key, $matching_keys);
            } else {
                $matching_keys = $label_relation['related']->newQuery()
                    ->where($label_relation['column'], 'like', '%' . $facet->search . '%')
                    ->pluck($label_relation['ownerKey'])
                    ->all();

                $filtered_base->whereIn($key, $matching_keys);
            }
        }

        $distinct_values = (int) (clone $filtered_base)->distinct()->count($key);

        $page_query = (clone $filtered_base)
            ->select($key)
            ->selectRaw('count(*) as aggregate')
            ->groupBy($key);

        $this->orderFacetPage($page_query, $model->getTable(), $key, $facet->sort, $label_relation);

        $rows = $page_query->forPage($facet->page, $facet->perPage)->get();

        $page_keys = $rows->pluck($key)->all();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row->{$key}] = (int) $row->aggregate;
        }

        $totals = $this->facetTotals($model, $permission_name, $key, $page_keys);
        $attributes = $this->resolveFacetLabels($model, $key, $page_keys, $facet->fields);

        $values = array_map(static fn (mixed $value): array => [
            'key' => $value,
            'total' => $totals[$value] ?? 0,
            'count' => $counts[$value] ?? 0,
            'attributes' => $attributes[$value] ?? [],
        ], $page_keys);

        return new FacetPage(array_values($values), $distinct_values, $facet->page, $facet->perPage);
    }

    /**
     * Facet over a BelongsToMany/MorphToMany relation's pivot: keys are related model
     * ids and the double counter counts distinct parent rows per related key. Parent
     * ACL and filters are enforced through a bounded id subquery, never a join into
     * the aggregated query, so parent scopes cannot collide with related columns. A
     * relation facet's own selection is excluded from its filtered counts, keeping
     * cross-filtering live. Labels, search and sort resolve on the related table.
     */
    private function facetRelationValues(ListRequestData $base, FacetQuery $facet, string $permission_name): FacetPage
    {
        $model = $base->model;
        $relation = $this->resolveManyRelation($model, (string) $facet->relation);

        if ($relation === null) {
            return new FacetPage([], 0, $facet->page, $facet->perPage);
        }

        $pivot_table = $relation->getTable();
        $qualified_foreign = $pivot_table . '.' . $relation->getForeignPivotKeyName();
        $qualified_related = $pivot_table . '.' . $relation->getRelatedPivotKeyName();
        $related = $relation->getRelated();
        $related_key_name = $relation->getRelatedKeyName();
        $related_key = $related->getTable() . '.' . $related_key_name;

        // A `relation.column` label field is a locale-scoped translation join keyed
        // by the pivot's related key; a bare one is a column on the related table.
        $label_translation = $facet->labelField !== null
            ? $this->relationTranslationLabel($related, $facet->labelField)
            : null;
        $label_bare = $label_translation === null && $facet->labelField !== null && $facet->labelField !== ''
            ? $related->getTable() . '.' . $facet->labelField
            : null;
        $label = $label_translation !== null
            ? $label_translation['table'] . '.' . $label_translation['column']
            : $label_bare;

        $filtered_ids = fn (): BaseQueryBuilder => $this->relationParentIds($model, $permission_name, $base->filters, $facet->relation);
        $acl_ids = fn (): BaseQueryBuilder => $this->relationParentIds($model, $permission_name, null, null);

        // Aggregate over the pivot on the query builder (never Eloquent) so heavy
        // related models are not hydrated from a partial select. Related global
        // scopes (soft deletes) are honoured through a bounded id subquery, and the
        // related/translation tables are joined only when a label is needed.
        $pivot_query = fn (): BaseQueryBuilder => $related->getConnection()->query()
            ->from($pivot_table)
            ->whereIn($qualified_related, $related->newQuery()->toBase()->select($related_key_name))
            ->when(
                $relation instanceof MorphToMany,
                fn (BaseQueryBuilder $query): BaseQueryBuilder => $query->where($pivot_table . '.' . $relation->getMorphType(), $relation->getMorphClass()),
            )
            ->when(
                $label_translation !== null,
                fn (BaseQueryBuilder $query): BaseQueryBuilder => $query->leftJoin(
                    $label_translation['table'],
                    fn (JoinClause $join): JoinClause => $join
                        ->on($label_translation['table'] . '.' . $label_translation['foreign'], '=', $qualified_related)
                        ->where($label_translation['table'] . '.locale', '=', $label_translation['locale']),
                ),
            )
            ->when(
                $label_bare !== null,
                fn (BaseQueryBuilder $query): BaseQueryBuilder => $query->leftJoin($related->getTable(), $related_key, '=', $qualified_related),
            );

        $searched = fn (BaseQueryBuilder $query): BaseQueryBuilder => $query->when(
            $facet->search !== null,
            fn (BaseQueryBuilder $q): BaseQueryBuilder => $q->where($label ?? $qualified_related, 'like', '%' . $facet->search . '%'),
        );

        $distinct_values = (int) $searched($pivot_query()->whereIn($qualified_foreign, $filtered_ids()))
            ->distinct()
            ->count($qualified_related);

        $page_query = $searched($pivot_query()->whereIn($qualified_foreign, $filtered_ids()))
            ->groupBy($qualified_related)
            ->selectRaw($qualified_related . ' as facet_key')
            ->selectRaw('count(distinct ' . $qualified_foreign . ') as aggregate');

        $this->orderRelationFacet($page_query, $qualified_related, $facet->sort, $label);

        $rows = $page_query->forPage($facet->page, $facet->perPage)->get();

        $page_keys = [];
        $counts = [];

        foreach ($rows as $row) {
            $page_keys[] = $row->facet_key;
            $counts[$row->facet_key] = (int) $row->aggregate;
        }

        $totals = $this->relationFacetTotals($pivot_query, $qualified_foreign, $qualified_related, $acl_ids(), $page_keys);
        $attributes = $this->resolveRelationFacetLabels($related, $related_key_name, $page_keys, $facet->fields);

        $values = array_map(static fn (mixed $value): array => [
            'key' => $value,
            'total' => $totals[$value] ?? 0,
            'count' => $counts[$value] ?? 0,
            'attributes' => $attributes[$value] ?? [],
        ], $page_keys);

        return new FacetPage(array_values($values), $distinct_values, $facet->page, $facet->perPage);
    }

    /**
     * Facet over a column reached through a single-hop to-one relation (e.g. group
     * by `place.country`): the parent rows are joined to the related table and
     * grouped by the related column, counting distinct parents per value. The value
     * is its own label, so no label resolution is needed. Parent ACL and filters are
     * enforced through a bounded id subquery, and the facet's own selection
     * (`<relation>.<column>`) is excluded to keep cross-filtering live.
     *
     * @param  array{relatedTable: string, foreignKey: string, ownerKey: string, column: string}  $to_one
     */
    private function facetRelatedColumnValues(ListRequestData $base, FacetQuery $facet, string $permission_name, array $to_one): FacetPage
    {
        $model = $base->model;
        $table = $model->getTable();
        $qualified_pk = $table . '.' . $model->getKeyName();
        $qualified_group = $to_one['relatedTable'] . '.' . $to_one['column'];

        $filtered_ids = fn (): BaseQueryBuilder => $this->relationParentIds($model, $permission_name, $base->filters, $facet->groupBy);
        $acl_ids = fn (): BaseQueryBuilder => $this->relationParentIds($model, $permission_name, null, null);

        $joined = fn (): BaseQueryBuilder => $model->getConnection()->query()
            ->from($table)
            ->join($to_one['relatedTable'], $table . '.' . $to_one['foreignKey'], '=', $to_one['relatedTable'] . '.' . $to_one['ownerKey']);

        $searched = fn (BaseQueryBuilder $query): BaseQueryBuilder => $query->when(
            $facet->search !== null,
            fn (BaseQueryBuilder $q): BaseQueryBuilder => $q->where($qualified_group, 'like', '%' . $facet->search . '%'),
        );

        $distinct_values = (int) $searched($joined()->whereIn($qualified_pk, $filtered_ids()))
            ->distinct()
            ->count($qualified_group);

        $page_query = $searched($joined()->whereIn($qualified_pk, $filtered_ids()))
            ->groupBy($qualified_group)
            ->selectRaw($qualified_group . ' as facet_key')
            ->selectRaw('count(distinct ' . $qualified_pk . ') as aggregate');

        $this->orderRelationFacet($page_query, $qualified_group, $facet->sort, $qualified_group);

        $rows = $page_query->forPage($facet->page, $facet->perPage)->get();

        $page_keys = [];
        $counts = [];

        foreach ($rows as $row) {
            $page_keys[] = $row->facet_key;
            $counts[$row->facet_key] = (int) $row->aggregate;
        }

        $totals = [];

        if ($page_keys !== []) {
            $total_rows = $joined()
                ->whereIn($qualified_pk, $acl_ids())
                ->whereIn($qualified_group, $page_keys)
                ->groupBy($qualified_group)
                ->selectRaw($qualified_group . ' as facet_key')
                ->selectRaw('count(distinct ' . $qualified_pk . ') as aggregate')
                ->get();

            foreach ($total_rows as $row) {
                $totals[$row->facet_key] = (int) $row->aggregate;
            }
        }

        $values = array_map(static fn (mixed $value): array => [
            'key' => $value,
            'total' => $totals[$value] ?? 0,
            'count' => $counts[$value] ?? 0,
            'attributes' => [$facet->groupBy => $value],
        ], $page_keys);

        return new FacetPage(array_values($values), $distinct_values, $facet->page, $facet->perPage);
    }

    /**
     * Resolve a `relation.column` group key to its single-hop to-one relation join,
     * or null when it is not a dotted path over a {@see BelongsTo}.
     *
     * @return array{relatedTable: string, foreignKey: string, ownerKey: string, column: string}|null
     */
    private function resolveToOneColumn(Model $model, string $group_by): ?array
    {
        $dot = mb_strpos($group_by, '.');

        if ($dot === false || mb_strpos($group_by, '.', $dot + 1) !== false) {
            return null;
        }

        $relation = mb_substr($group_by, 0, $dot);

        if (! method_exists($model, $relation)) {
            return null;
        }

        try {
            $relation_object = $model->newInstance()->{$relation}();
        } catch (Throwable) {
            return null;
        }

        if (! $relation_object instanceof BelongsTo) {
            return null;
        }

        return [
            'relatedTable' => $relation_object->getRelated()->getTable(),
            'foreignKey' => $relation_object->getForeignKeyName(),
            'ownerKey' => $relation_object->getOwnerKeyName(),
            'column' => mb_substr($group_by, $dot + 1),
        ];
    }

    /**
     * Bounded parent-id subquery: ACL plus the request filters (minus the facet's
     * own relation selection, when given), selecting only the parent key so it can
     * feed a `whereIn` without joining into the aggregated query.
     */
    private function relationParentIds(Model $model, string $permission_name, ?FiltersGroup $filters, ?string $relation): BaseQueryBuilder
    {
        $query = $model->newQuery();
        $this->auth->applyAclFiltersToQuery($query, $permission_name);

        if ($filters instanceof FiltersGroup) {
            $this->query_builder->applyFilters(
                $query,
                $relation !== null ? $this->excludeFacetField($filters, $relation) : $filters,
            );
        }

        return $query->toBase()->select($model->getTable() . '.' . $model->getKeyName());
    }

    /**
     * `total` per related key: the distribution ignoring the request filters (ACL
     * only), restricted to the page's keys so it stays bounded.
     *
     * @param  callable(): BaseQueryBuilder  $pivot_query
     * @param  list<mixed>  $page_keys
     * @return array<mixed, int>
     */
    private function relationFacetTotals(callable $pivot_query, string $qualified_foreign, string $qualified_related, BaseQueryBuilder $acl_ids, array $page_keys): array
    {
        if ($page_keys === []) {
            return [];
        }

        $rows = $pivot_query()
            ->whereIn($qualified_foreign, $acl_ids)
            ->whereIn($qualified_related, $page_keys)
            ->groupBy($qualified_related)
            ->selectRaw($qualified_related . ' as facet_key')
            ->selectRaw('count(distinct ' . $qualified_foreign . ') as aggregate')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[$row->facet_key] = (int) $row->aggregate;
        }

        return $totals;
    }

    /**
     * Resolve display fields for a relation facet's page keys as bounded reads over
     * the related table. Base columns only here; a translated label column is
     * resolved by {@see resolveTranslatedRelationLabels} in the caller.
     *
     * @param  list<mixed>  $page_keys
     * @param  list<string>  $fields
     * @return array<mixed, array<string, mixed>>
     */
    private function resolveRelationFacetLabels(Model $related, string $related_key, array $page_keys, array $fields): array
    {
        if ($fields === [] || $page_keys === []) {
            return [];
        }

        $columns = [];
        $translation_fields = [];

        foreach ($fields as $field) {
            if (! str_contains($field, '.')) {
                $columns[] = $field;

                continue;
            }

            $translation = $this->relationTranslationLabel($related, $field);

            if ($translation !== null) {
                $translation_fields[] = ['field' => $field, 'translation' => $translation];
            }
        }

        $resolved = [];

        $this->resolveRelationBaseLabels($related, $related_key, $page_keys, array_values(array_unique($columns)), $resolved);

        foreach ($translation_fields as $spec) {
            $this->resolveTranslatedRelationLabel($page_keys, $spec['field'], $spec['translation'], $resolved);
        }

        return $resolved;
    }

    /**
     * @param  list<mixed>  $page_keys
     * @param  list<string>  $columns
     * @param  array<mixed, array<string, mixed>>  $resolved
     */
    private function resolveRelationBaseLabels(Model $related, string $related_key, array $page_keys, array $columns, array &$resolved): void
    {
        if ($columns === []) {
            return;
        }

        // Read on the query builder (applying the related scopes, never Eloquent
        // hydration) so a heavy related model is not built from a partial select.
        $rows = $related->newQuery()
            ->toBase()
            ->whereIn($related->getTable() . '.' . $related_key, $page_keys)
            ->get(array_values(array_unique([$related_key, ...$columns])));

        foreach ($rows as $row) {
            $value = $row->{$related_key};

            foreach ($columns as $column) {
                $resolved[$value] ??= [];
                $resolved[$value][$column] ??= $row->{$column};
            }
        }
    }

    /**
     * Resolve a locale-scoped translated label for a relation facet's page keys in
     * one bounded read over the translation table.
     *
     * @param  list<mixed>  $page_keys
     * @param  array{model: Model, table: string, foreign: string, column: string, locale: string}  $translation
     * @param  array<mixed, array<string, mixed>>  $resolved
     */
    private function resolveTranslatedRelationLabel(array $page_keys, string $field, array $translation, array &$resolved): void
    {
        $rows = $translation['model']->newQuery()
            ->whereIn($translation['foreign'], $page_keys)
            ->where('locale', $translation['locale'])
            ->get([$translation['foreign'], $translation['column']]);

        foreach ($rows as $row) {
            $value = $row->{$translation['foreign']};
            $resolved[$value] ??= [];
            $resolved[$value][$field] ??= $row->{$translation['column']};
        }
    }

    /**
     * Parse a `relation.column` label spec into a locale-scoped translation join
     * target when `relation` is a {@see HasMany} translation relation on the related
     * model; null otherwise.
     *
     * @return array{model: Model, table: string, foreign: string, column: string, locale: string}|null
     */
    private function relationTranslationLabel(Model $related, string $spec): ?array
    {
        $dot = mb_strpos($spec, '.');

        if ($dot === false || mb_strpos($spec, '.', $dot + 1) !== false) {
            return null;
        }

        $relation = mb_substr($spec, 0, $dot);

        if (! method_exists($related, $relation)) {
            return null;
        }

        try {
            $relation_object = $related->newInstance()->{$relation}();
        } catch (Throwable) {
            return null;
        }

        if (! $relation_object instanceof HasMany) {
            return null;
        }

        $translation_model = $relation_object->getRelated();

        return [
            'model' => $translation_model,
            'table' => $translation_model->getTable(),
            'foreign' => $relation_object->getForeignKeyName(),
            'column' => mb_substr($spec, $dot + 1),
            'locale' => LocaleContext::get(),
        ];
    }

    /**
     * Reflectively resolve a BelongsToMany/MorphToMany relation by name, or null
     * when it is absent or of another kind.
     */
    private function resolveManyRelation(Model $model, string $relation): ?BelongsToMany
    {
        if ($relation === '' || ! method_exists($model, $relation)) {
            return null;
        }

        try {
            $object = $model->newInstance()->{$relation}();
        } catch (Throwable) {
            return null;
        }

        return $object instanceof BelongsToMany ? $object : null;
    }

    /**
     * Order a relation facet's page: by the grouped count, the related key, or a
     * related label column (a real column in this joined query, so no subquery is
     * needed). A key tiebreak keeps count paging deterministic.
     */
    private function orderRelationFacet(BaseQueryBuilder $query, string $key, FacetSort $sort, ?string $label): void
    {
        match ($sort) {
            FacetSort::CountDesc => $query->orderByRaw('aggregate desc')->orderBy($key),
            FacetSort::CountAsc => $query->orderByRaw('aggregate asc')->orderBy($key),
            FacetSort::KeyAsc => $query->orderBy($key),
            FacetSort::KeyDesc => $query->orderByDesc($key),
            FacetSort::LabelAsc => $label !== null ? $query->orderBy($label) : $query->orderBy($key),
            FacetSort::LabelDesc => $label !== null ? $query->orderByDesc($label) : $query->orderByDesc($key),
        };
    }

    /**
     * Page-scoped freshness fingerprint: filtered total + current page id/updated_at rows,
     * plus optional presence of client snapshot ids (on_page / off_page / gone).
     *
     * Reuses list auth, ACL injection, filters, sort and pagination. Selects only
     * primary key + updated_at (when the model has timestamps).
     *
     * Rows are read via the base query builder (no Eloquent hydration) so model
     * global `with()` scopes, `retrieved` hooks, and missing-attribute guards
     * cannot blow up when FK columns were intentionally omitted from the select.
     *
     * @return CrudResult data shape: `{ total, items, presence }`
     */
    public function freshness(ListRequestData $requestData): CrudResult
    {
        $model = $requestData->model;

        $permission_name = $this->auth->ensurePermission(
            $requestData->request,
            $model->getTable(),
            'select',
            $model->getConnectionName(),
        );

        $this->auth->injectAclFilters($requestData, $permission_name);

        $query = $model->newQuery();
        $this->query_builder->prepareQuery($query, $requestData);

        // Drop eager loads / wide selects from the list pipeline; fingerprint only.
        $query->withoutEagerLoads();
        $table = $model->getTable();
        $key_name = $model->getKeyName();
        $updated_at_column = $model->usesTimestamps() ? $model->getUpdatedAtColumn() : null;

        $select_columns = is_array($key_name)
            ? array_map(static fn (string $key): string => $table . '.' . $key, $key_name)
            : [$table . '.' . $key_name];

        if (is_string($updated_at_column) && $updated_at_column !== '') {
            $select_columns[] = $table . '.' . $updated_at_column;
        }

        $query->select($select_columns);

        $total_records = (clone $query)->count();

        // Page window on a clone so the filtered base query stays usable for presence.
        $page_query = clone $query;
        match (true) {
            $requestData->page !== null => $page_query
                ->skip($requestData->from - 1)
                ->take($requestData->to - $requestData->from + 1),
            $requestData->from !== null => tap($page_query, static function (Builder $q) use ($requestData): void {
                $q->skip($requestData->from - 1);

                if ($requestData->to !== null) {
                    $q->take($requestData->to - $requestData->from + 1);
                }
            }),
            isset($requestData->limit) => $page_query->take($requestData->take),
            default => $page_query,
        };

        $rows = $requestData->count
            ? collect()
            : $page_query->toBase()->get();

        $key_columns = is_array($key_name) ? $key_name : [$key_name];

        $items = $rows->map(
            fn (object $row): array => $this->mapFreshnessRow($row, $key_columns, $updated_at_column),
        )->values()->all();

        $presence = $this->buildFreshnessPresence(
            $query,
            $items,
            $key_name,
            $key_columns,
            $table,
            $updated_at_column,
            $this->normalizeFreshnessCheckIds($requestData),
        );

        return new CrudResult(
            data: [
                'total' => $total_records,
                'items' => $items,
                'presence' => $presence,
            ],
            meta: new CrudMeta(
                totalRecords: $total_records,
                currentRecords: count($items),
                currentPage: $requestData->page,
                totalPages: $requestData->page !== null ? $requestData->calculateTotalPages($total_records) : null,
                pagination: $requestData->pagination,
                from: $requestData->from,
                to: $requestData->to,
                class: $model::class,
                table: $model->getTable(),
                cachedAt: Date::now(),
            ),
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

        $updated_records = new Collection();
        $found_count = 0;
        $model->getConnection()->transaction(function () use ($found_records, $updated_records, $changes, &$found_count): void {
            foreach ($found_records as $found_record) {
                $found_count++;

                /** @psalm-suppress InvalidArgument */
                if ($found_record->update($changes)) {
                    $updated_records->add($found_record->fresh());
                }
            }
        });
        throw_if($found_count === 0 && $requestData->request->has('id'), ModelNotFoundException::class, 'No model Found');

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

        $found_count = 0;
        $deleted_count = 0;
        $model->getConnection()->transaction(function () use ($found_records, &$found_count, &$deleted_count): void {
            foreach ($found_records as $found_record) {
                $found_count++;

                if ($found_record->forceDelete()) {
                    $deleted_count++;
                }
            }
        });
        throw_if($found_count === 0 && $requestData->request->has('id'), ModelNotFoundException::class, 'No model Found');

        return new CrudResult(
            data: ['deleted' => $deleted_count],
            statusCode: Response::HTTP_OK,
        );
    }

    public function doActivateOperation(ModifyRequestData $requestData, string $operation): CrudResult
    {
        $model = $requestData->model;
        $is_activate = $operation === 'activate';

        // activate restores a soft-deleted record (restore permission); inactivate
        // soft-deletes a live one (delete permission). The permission must match the
        // operation on both gates — previously the entity gate distinguished them but
        // the user gate always required 'restore', so soft-deleting demanded the
        // restore permission.
        $required_permission = $is_activate ? 'restore' : 'delete';
        $this->assertCrudWriteAllowed($model, $required_permission);
        $this->auth->ensurePermission($requestData->request, $model->getTable(), $required_permission, $model->getConnectionName());
        $key_value = $this->getModelKeyValue($requestData);
        $found_record = $this->newQueryWithTrashed($model)
            ->where($this->keyValueToWhereCondition($model, $key_value))
            ->firstOrFail();

        if ($is_activate) {
            throw_if(! method_exists($found_record, 'restore') || ! $found_record->restore(), LogicException::class, 'Record not activated');

            return new CrudResult(
                data: $found_record,
            );
        }

        throw_unless($found_record->delete(), LogicException::class, 'Record not inactivated');

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

    /**
     * Active modifications for an entity type (publisher approval inbox).
     *
     * @return CrudResult{data: list<array<string, mixed>>}
     */
    public function pendingApprovals(CrudRequestData $requestData): CrudResult
    {
        $model = $requestData->model;
        $this->auth->ensurePermission(
            $requestData->request,
            $model->getTable(),
            'approve',
            $model->getConnectionName(),
        );

        $connection = $model->getConnectionName();
        $modification_prototype = (new Modification())->setConnection($connection);

        $modifications = $modification_prototype->newQuery()
            ->where('modifiable_type', $model::class)
            ->activeOnly()
            ->with(['modifiable', 'modifier'])
            ->oldest()
            ->get();

        $rows = $modifications->map(static function (Modification $modification): Fluent {
            $modifiable = $modification->modifiable;
            $label = null;

            if ($modifiable instanceof Model) {
                $attributes = $modifiable->getAttributes();
                $label = $attributes['title'] ?? $attributes['name'] ?? null;
            }

            return new Fluent([
                'id' => $modification->modifiable_id,
                'modification_id' => $modification->getKey(),
                'title' => $label,
                'created_at' => $modification->getAttribute('created_at'),
                'approvers_required' => $modification->approvers_required,
                'approvers_remaining' => $modification->approversRemaining,
                'modifier_id' => $modification->modifier_id,
                'modifier_type' => $modification->modifier_type,
            ]);
        })->values();

        return new CrudResult(
            data: $rows,
            meta: new CrudMeta(
                totalRecords: $rows->count(),
                currentRecords: $rows->count(),
                class: $model::class,
                table: $model->getTable(),
                cachedAt: Date::now(),
            ),
        );
    }

    /**
     * Soft-kept disapproval for the authenticated modifier on one record (editor rejection banner).
     */
    public function latestDisapproval(CrudRequestData $requestData): CrudResult
    {
        $model = $requestData->model;
        $this->auth->ensurePermission(
            $requestData->request,
            $model->getTable(),
            'select',
            $model->getConnectionName(),
        );

        $user = Auth::user();
        throw_unless($user instanceof User, LogicException::class, 'Authenticated user is required.');

        $record_id = $requestData->request->input('id');
        $found_record = $model->newQuery()->whereKey($record_id)->firstOrFail();

        $connection = $found_record->getConnectionName();
        $modification_prototype = (new Modification())->setConnection($connection);

        $modifier_types = array_values(array_unique([
            $user::class,
            User::class,
            \App\Models\User::class,
        ]));

        /** @var Modification|null $modification */
        $modification = $modification_prototype->newQuery()
            ->where('modifiable_type', $model::class)
            ->where('modifiable_id', $found_record->getKey())
            ->where('modifier_id', $user->getKey())
            ->whereIn('modifier_type', $modifier_types)
            ->inactiveOnly()
            ->whereHas('disapprovals')
            ->with(['disapprovals' => static fn ($query) => $query->latest('id')])
            ->latest('id')
            ->first();

        if ($modification === null) {
            return new CrudResult(
                data: null,
                meta: new CrudMeta(
                    totalRecords: 0,
                    currentRecords: 0,
                    class: $model::class,
                    table: $model->getTable(),
                    cachedAt: Date::now(),
                ),
            );
        }

        /** @var Disapproval|null $disapproval */
        $disapproval = $modification->disapprovals->first();

        return new CrudResult(
            data: new Fluent([
                'id' => $modification->modifiable_id,
                'modification_id' => $modification->getKey(),
                'reason' => $disapproval?->reason,
                'modifications' => $modification->modifications,
                'disapproved_at' => $disapproval?->getAttribute('created_at'),
                'disapprover_id' => $disapproval?->disapprover_id,
                'disapprover_type' => $disapproval?->disapprover_type,
            ]),
            meta: new CrudMeta(
                totalRecords: 1,
                currentRecords: 1,
                class: $model::class,
                table: $model->getTable(),
                cachedAt: Date::now(),
            ),
        );
    }

    /**
     * @param  array{related: Model, relatedTable: string, ownerKey: string, column: string}|null  $label_relation
     */
    private function orderFacetPage(\Illuminate\Database\Query\Builder $query, string $base_table, string $key, FacetSort $sort, ?array $label_relation): void
    {
        match ($sort) {
            // A stable key tiebreak keeps paging deterministic when counts tie.
            FacetSort::CountDesc => $query->orderByRaw('count(*) desc')->orderBy($key),
            FacetSort::CountAsc => $query->orderByRaw('count(*) asc')->orderBy($key),
            FacetSort::KeyAsc => $query->orderBy($key),
            FacetSort::KeyDesc => $query->orderByDesc($key),
            FacetSort::LabelAsc => $this->orderFacetByLabel($query, $base_table, $key, $label_relation, 'asc'),
            FacetSort::LabelDesc => $this->orderFacetByLabel($query, $base_table, $key, $label_relation, 'desc'),
        };
    }

    /**
     * Order a grouped facet page by a relation label without joining: a
     * correlated subquery yields each group's label (the label is functionally
     * dependent on the group key, so one row). With no resolvable label relation
     * it falls back to the key, so a label sort never silently does nothing.
     *
     * @param  array{related: Model, relatedTable: string, ownerKey: string, column: string}|null  $label_relation
     */
    private function orderFacetByLabel(\Illuminate\Database\Query\Builder $query, string $base_table, string $key, ?array $label_relation, string $direction): void
    {
        if ($label_relation === null) {
            $query->orderBy($key, $direction);

            return;
        }

        if (isset($label_relation['translation'])) {
            // Translated label: the translation row is keyed by the group key.
            $translation = $label_relation['translation'];
            $subquery = $translation['model']->newQuery()
                ->select($translation['column'])
                ->where('locale', $translation['locale'])
                ->whereColumn($translation['model']->getTable() . '.' . $translation['foreign'], $base_table . '.' . $key)
                ->limit(1)
                ->toBase();

            $query->orderBy($subquery, $direction);

            return;
        }

        $subquery = $label_relation['related']->newQuery()
            ->select($label_relation['column'])
            ->whereColumn($label_relation['relatedTable'] . '.' . $label_relation['ownerKey'], $base_table . '.' . $key)
            ->limit(1)
            ->toBase();

        $query->orderBy($subquery, $direction);
    }

    /**
     * Resolve a `relation.column` label target to its single-hop BelongsTo — only
     * when the relation's foreign key is the facet's group key, so the label can
     * be reached from the group key alone.
     *
     * @return array{related: Model, relatedTable: string, ownerKey: string, column: string}|null
     */
    private function resolveLabelRelation(Model $model, string $key, string $label_field): ?array
    {
        $dot = mb_strpos($label_field, '.');

        if ($dot === false || mb_strpos($label_field, '.', $dot + 1) !== false) {
            return null;
        }

        $relation = mb_substr($label_field, 0, $dot);
        $target = $this->labelRelationTarget($model, $key, $relation);

        return $target === null ? null : [...$target, 'column' => mb_substr($label_field, $dot + 1)];
    }

    /**
     * Resolve the label target a facet's group key can reach in a single hop:
     * either a declared {@see BelongsTo} whose foreign key is the group key, or a
     * {@see FacetLabelSource} the model registers for a foreign key exposed without
     * a relation (e.g. through an accessor). Both are keyed by the group key alone,
     * so no join enters the aggregated query.
     *
     * @return array{related: Model, relatedTable: string, ownerKey: string}|null
     */
    private function labelRelationTarget(Model $model, string $key, string $relation): ?array
    {
        // A model-declared source wins: it is an explicit choice and can express a
        // translated label that a BelongsTo alone cannot.
        $declared = $this->declaredLabelTarget($model, $key, $relation);

        if ($declared !== null) {
            return $declared;
        }

        if (method_exists($model, $relation)) {
            try {
                $relation_object = $model->newInstance()->{$relation}();
            } catch (Throwable) {
                $relation_object = null;
            }

            if ($relation_object instanceof BelongsTo && $key === $this->facetKey($relation_object->getForeignKeyName())) {
                $related = $relation_object->getRelated();

                return [
                    'related' => $related,
                    'relatedTable' => $related->getTable(),
                    'ownerKey' => $relation_object->getOwnerKeyName(),
                ];
            }
        }

        return null;
    }

    /**
     * Build a label target from a model-declared {@see FacetLabelSource} when its
     * foreign key is the facet's group key.
     *
     * @return array{related: Model, relatedTable: string, ownerKey: string}|null
     */
    private function declaredLabelTarget(Model $model, string $key, string $relation): ?array
    {
        if (! $model instanceof ProvidesFacetLabelSources) {
            return null;
        }

        $source = $model->facetLabelSources()[$relation] ?? null;

        if (! $source instanceof FacetLabelSource || $key !== $this->facetKey($source->foreignKey)) {
            return null;
        }

        $related = new $source->relatedClass;

        if (! $related instanceof Model) {
            return null;
        }

        $target = [
            'related' => $related,
            'relatedTable' => $related->getTable(),
            'ownerKey' => $source->ownerKey,
        ];

        $translation = $this->facetLabelSourceTranslation($related, $source);

        if ($translation !== null) {
            $target['translation'] = $translation;
        }

        return $target;
    }

    /**
     * Resolve a declared source's locale-scoped translation target (the related
     * model's HasMany translation relation), keyed by the facet group key since the
     * translation's foreign key equals the related id equals the group key value.
     *
     * @return array{model: Model, foreign: string, column: string, locale: string}|null
     */
    private function facetLabelSourceTranslation(Model $related, FacetLabelSource $source): ?array
    {
        if ($source->translationRelation === null || $source->translationColumn === null) {
            return null;
        }

        if (! method_exists($related, $source->translationRelation)) {
            return null;
        }

        try {
            $relation_object = $related->{$source->translationRelation}();
        } catch (Throwable) {
            return null;
        }

        if (! $relation_object instanceof HasMany) {
            return null;
        }

        return [
            'model' => $relation_object->getRelated(),
            'foreign' => $relation_object->getForeignKeyName(),
            'column' => $source->translationColumn,
            'locale' => LocaleContext::get(),
        ];
    }

    /**
     * `total` per key: the value distribution ignoring the request filters (ACL
     * only), restricted to the page's keys so it stays bounded.
     *
     * @param  list<mixed>  $page_keys
     * @return array<mixed, int>
     */
    private function facetTotals(Model $model, string $permission_name, string $key, array $page_keys): array
    {
        if ($page_keys === []) {
            return [];
        }

        $query = $model->newQuery();
        $this->auth->applyAclFiltersToQuery($query, $permission_name);

        $rows = $query->toBase()
            ->select($key)
            ->selectRaw('count(*) as aggregate')
            ->whereIn($key, $page_keys)
            ->groupBy($key)
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals[$row->{$key}] = (int) $row->aggregate;
        }

        return $totals;
    }

    /**
     * The key≠label second step: resolve the requested display fields per key in
     * bounded reads over the page's keys — never a join into the aggregated query,
     * so `GROUP BY` stays on one portable column. Base-table columns resolve
     * directly; a single-dot `relation.column` resolves through a single-hop
     * {@see BelongsTo} whose foreign key is the facet key (e.g. group by
     * `license_id`, label `license.uuid`). Multi-hop paths and non-BelongsTo
     * relations are skipped; label search/sort remain deferred.
     *
     * @param  list<mixed>  $page_keys
     * @param  list<string>  $fields
     * @return array<mixed, array<string, mixed>>
     */
    private function resolveFacetLabels(Model $model, string $key, array $page_keys, array $fields): array
    {
        if ($fields === [] || $page_keys === []) {
            return [];
        }

        $columns = [];
        $relation_fields = [];

        foreach ($fields as $field) {
            $dot = mb_strpos($field, '.');

            if ($dot === false) {
                $columns[] = $field;

                continue;
            }

            // Single-hop only: a second dot is a multi-hop path, deferred.
            if (mb_strpos($field, '.', $dot + 1) !== false) {
                continue;
            }

            $relation = mb_substr($field, 0, $dot);
            $relation_fields[$relation][] = ['field' => $field, 'column' => mb_substr($field, $dot + 1)];
        }

        $resolved = [];

        $this->resolveBaseColumnLabels($model, $key, $page_keys, array_values(array_unique($columns)), $resolved);

        foreach ($relation_fields as $relation => $specs) {
            $this->resolveRelationLabels($model, $key, $page_keys, $relation, $specs, $resolved);
        }

        return $resolved;
    }

    /**
     * @param  list<mixed>  $page_keys
     * @param  list<string>  $columns
     * @param  array<mixed, array<string, mixed>>  $resolved
     */
    private function resolveBaseColumnLabels(Model $model, string $key, array $page_keys, array $columns, array &$resolved): void
    {
        if ($columns === []) {
            return;
        }

        $rows = $model->newQuery()
            ->whereIn($key, $page_keys)
            ->get(array_values(array_unique([$key, ...$columns])));

        foreach ($rows as $row) {
            $value = $row->{$key};

            foreach ($columns as $column) {
                $resolved[$value] ??= [];
                $resolved[$value][$column] ??= $row->{$column};
            }
        }
    }

    /**
     * @param  list<mixed>  $page_keys
     * @param  list<array{field: string, column: string}>  $specs
     * @param  array<mixed, array<string, mixed>>  $resolved
     */
    private function resolveRelationLabels(Model $model, string $key, array $page_keys, string $relation, array $specs, array &$resolved): void
    {
        // Only a single-hop label target whose key is the facet key can be resolved
        // from the page's keys without a join (a BelongsTo or a declared source).
        $target = $this->labelRelationTarget($model, $key, $relation);

        if ($target === null) {
            return;
        }

        if (isset($target['translation'])) {
            $this->resolveTranslatedFacetLabels($target['translation'], $page_keys, $specs, $resolved);

            return;
        }

        $owner_key = $target['ownerKey'];
        $columns = array_column($specs, 'column');

        $related = $target['related']->newQuery()
            ->whereIn($owner_key, $page_keys)
            ->get(array_values(array_unique([$owner_key, ...$columns])))
            ->keyBy($owner_key);

        foreach ($page_keys as $page_key) {
            $row = $related->get($page_key);

            foreach ($specs as $spec) {
                $resolved[$page_key] ??= [];
                $resolved[$page_key][$spec['field']] = $row?->{$spec['column']};
            }
        }
    }

    /**
     * Resolve a base-column FK facet's translated labels for the page keys: the
     * translation row is keyed by the group key, so one bounded locale-scoped read
     * fills every requested field with the translated column.
     *
     * @param  array{model: Model, foreign: string, column: string, locale: string}  $translation
     * @param  list<mixed>  $page_keys
     * @param  list<array{field: string, column: string}>  $specs
     * @param  array<mixed, array<string, mixed>>  $resolved
     */
    private function resolveTranslatedFacetLabels(array $translation, array $page_keys, array $specs, array &$resolved): void
    {
        $rows = $translation['model']->newQuery()
            ->whereIn($translation['foreign'], $page_keys)
            ->where('locale', $translation['locale'])
            ->get([$translation['foreign'], $translation['column']])
            ->keyBy($translation['foreign']);

        foreach ($page_keys as $page_key) {
            $row = $rows->get($page_key);

            foreach ($specs as $spec) {
                $resolved[$page_key] ??= [];
                $resolved[$page_key][$spec['field']] = $row?->{$translation['column']};
            }
        }
    }

    /**
     * Rebuild a FiltersGroup without the nodes targeting the given facet field, so a
     * facet's own selection does not suppress the counts of its other values.
     */
    private function excludeFacetField(FiltersGroup $filters, string $field): FiltersGroup
    {
        $kept = [];

        foreach ($filters->filters as $node) {
            if ($node instanceof FiltersGroup) {
                $kept[] = $this->excludeFacetField($node, $field);

                continue;
            }

            if ($node instanceof Filter && $this->facetFieldMatches($node->property, $field)) {
                continue;
            }

            $kept[] = $node;
        }

        return new FiltersGroup($kept, $filters->operator);
    }

    private function facetFieldMatches(string $property, string $field): bool
    {
        if ($property === $field) {
            return true;
        }

        // Relation facet: the field is the relation name and its selection targets a
        // column on it (field `categories`, property `categories.id`), so the whole
        // relation membership filter is the facet's own selection.
        if (str_contains($property, '.') && strstr($property, '.', true) === $field) {
            return true;
        }

        return $this->facetKey($property) === $this->facetKey($field);
    }

    /**
     * The bare column name (last dot segment) used as the facet's response key.
     */
    private function facetKey(string $field): string
    {
        $position = mb_strrpos($field, '.');

        return $position === false ? $field : mb_substr($field, $position + 1);
    }

    /**
     * @param  array<int, string>  $key_columns
     * @return array{id: mixed, updated_at: string|null}
     */
    private function mapFreshnessRow(object $row, array $key_columns, ?string $updated_at_column): array
    {
        $id = count($key_columns) === 1
            ? $row->{$key_columns[0]}
            : array_map(static fn (string $key): mixed => $row->{$key}, $key_columns);

        return [
            'id' => $id,
            'updated_at' => $this->formatFreshnessUpdatedAt(
                is_string($updated_at_column) && $updated_at_column !== ''
                    ? ($row->{$updated_at_column} ?? null)
                    : null,
            ),
        ];
    }

    private function formatFreshnessUpdatedAt(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            // toBase() returns MySQL "Y-m-d H:i:s" (app TZ). Normalize to ISO-8601 UTC
            // so clients can compare with Eloquent JSON timestamps without false positives.
            return Date::parse($value)->utc()->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<int|string>
     */
    private function normalizeFreshnessCheckIds(ListRequestData $requestData): array
    {
        $raw = $requestData->request->input('check_ids', []);

        if (! is_array($raw)) {
            return [];
        }

        $ids = [];

        foreach ($raw as $value) {
            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }

            if (is_int($value) || is_string($value)) {
                $ids[] = $value;
            } elseif (is_numeric($value)) {
                $ids[] = (string) $value;
            }

            if (count($ids) >= 100) {
                break;
            }
        }

        return array_values(array_unique($ids, SORT_REGULAR));
    }

    /**
     * Classify snapshot ids against the filtered set (no page window) and current page items.
     *
     * @param  list<array{id: mixed, updated_at: string|null}>  $items
     * @param  string|array<int, string>  $key_name
     * @param  array<int, string>  $key_columns
     * @param  list<int|string>  $check_ids
     * @return list<array{id: int|string, status: 'on_page'|'off_page'|'gone', updated_at: string|null}>
     */
    private function buildFreshnessPresence(
        Builder $filtered_query,
        array $items,
        string|array $key_name,
        array $key_columns,
        string $table,
        ?string $updated_at_column,
        array $check_ids,
    ): array {
        if ($check_ids === [] || is_array($key_name)) {
            return [];
        }

        $page_ids = [];

        foreach ($items as $item) {
            $page_ids[(string) $item['id']] = true;
        }

        $found_rows = (clone $filtered_query)
            ->whereIn($table . '.' . $key_name, $check_ids)
            ->toBase()
            ->get();

        $found_by_id = [];

        foreach ($found_rows as $row) {
            $mapped = $this->mapFreshnessRow($row, $key_columns, $updated_at_column);
            $found_by_id[(string) $mapped['id']] = $mapped;
        }

        $presence = [];

        foreach ($check_ids as $id) {
            $key = (string) $id;

            if (! isset($found_by_id[$key])) {
                $presence[] = [
                    'id' => $id,
                    'status' => 'gone',
                    'updated_at' => null,
                ];

                continue;
            }

            $presence[] = [
                'id' => $found_by_id[$key]['id'],
                'status' => isset($page_ids[$key]) ? 'on_page' : 'off_page',
                'updated_at' => $found_by_id[$key]['updated_at'],
            ];
        }

        return $presence;
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
        $connection = $found_record->getConnection();
        $modification_prototype = (new Modification())->setConnection($connection->getName());

        $connection->transaction(function () use ($requestData, $model, $found_record, $user, $operation, $modification_prototype): void {
            if (isset($requestData->changes['modification'])) {
                $modification = $modification_prototype->newQuery()
                    ->where('modifiable_type', $model::class)
                    ->where('modifiable_id', $requestData->primaryKey)
                    ->whereKey($requestData->changes['modification'])
                    ->lockForUpdate()
                    ->sole();

                $reason = $requestData->changes['reason'] ?? null;
                $vote_reason = is_string($reason) ? $reason : null;
                $this->castApprovalVote($user, $modification, $found_record, $operation, $vote_reason);
            } else {
                $modifications = $modification_prototype->newQuery()
                    ->where('modifiable_type', $found_record::class)
                    ->where('modifiable_id', $found_record->getKey())
                    ->activeOnly()
                    ->oldest()
                    ->lockForUpdate()
                    ->cursor();

                throw_if($modifications->isEmpty(), LogicException::class, sprintf('No modifications to be %sd', $operation));

                $reason = $requestData->changes['reason'] ?? null;
                $vote_reason = is_string($reason) ? $reason : null;

                foreach ($modifications as $modification) {
                    $this->castApprovalVote($user, $modification, $found_record, $operation, $vote_reason);
                }
            }
        });

        $found_record->refresh();

        return new CrudResult(
            data: $found_record,
        );
    }

    /**
     * Cast a vote using the modification owner's connection.
     *
     * @param  "approve"|"disapprove"  $operation
     */
    private function castApprovalVote(User $user, Modification $modification, Model $modifiable, string $operation, ?string $reason): void
    {
        $connection = $modification->getConnectionName();
        $is_approval = $operation === 'approve';
        $modification->setRelation('modifiable', $modifiable);

        if (! $user->isAuthorizedToCastApprovalVote($modification, $is_approval)) {
            return;
        }

        $vote = $is_approval
            ? (new Approval())->setConnection($connection)
            : (new Disapproval())->setConnection($connection);
        $opposite_vote = $is_approval
            ? (new Disapproval())->setConnection($connection)
            : (new Approval())->setConnection($connection);
        $actor_id_column = $is_approval ? 'approver_id' : 'disapprover_id';
        $actor_type_column = $is_approval ? 'approver_type' : 'disapprover_type';
        $opposite_id_column = $is_approval ? 'disapprover_id' : 'approver_id';
        $opposite_type_column = $is_approval ? 'disapprover_type' : 'approver_type';

        $opposite_vote->newQuery()->where([
            $opposite_id_column => $user->getKey(),
            $opposite_type_column => $user::class,
            'modification_id' => $modification->getKey(),
        ])->delete();

        $vote->newQuery()->updateOrCreate([
            $actor_id_column => $user->getKey(),
            $actor_type_column => $user::class,
            'modification_id' => $modification->getKey(),
        ], [
            'reason' => $reason,
        ]);

        $modification->refresh();
        $remaining = $is_approval
            ? $modification->approversRemaining
            : $modification->disapproversRemaining;

        if ($remaining !== 0) {
            return;
        }

        if ($modification->modifiable_id === null) {
            throw_unless(is_string($modification->modifiable_type), LogicException::class, 'Modifiable type is required.');
            $modifiable_type = $modification->modifiable_type;

            /** @var Model $modifiable */
            $modifiable = (new $modifiable_type())->setConnection($connection);
        } else {
            /** @var Model $modifiable */
            $modifiable = $modification->modifiable;
        }

        $modifiable->applyModificationChanges($modification, $is_approval);
    }

    /**
     * Both operations are governed by the single `lock` permission, mirroring
     * {@see doApproveOperation()} where `approve` also governs `disapprove`.
     *
     * @param  "lock"|"unlock"  $operation
     */
    private function doLockOperation(ModifyRequestData $requestData, string $operation): CrudResult
    {
        $model = $requestData->model;
        $this->assertCrudWriteAllowed($model, $operation);

        throw_unless(class_uses_trait($model, HasLocks::class), BadMethodCallException::class, $model::class . " doesn't support locks");
        $this->auth->ensurePermission($requestData->request, $model->getTable(), 'lock', $model->getConnectionName());
        $key_value = $this->getModelKeyValue($requestData);

        $found_records = $model->newQuery()->where($this->keyValueToWhereCondition($model, $key_value))->lazy(100);
        $found_count = 0;
        $target_locked_state = $operation === 'lock';
        $affected_records = new Collection();
        $model->getConnection()->transaction(function () use ($found_records, $affected_records, $operation, $requestData, $target_locked_state, &$found_count): void {
            foreach ($found_records as $found_record) {
                $found_count++;
                $already_in_target_state = $target_locked_state === $this->recordIsLocked($found_record);

                if ($requestData->request->has('id')) {
                    throw_if(
                        $already_in_target_state,
                        AlreadyLockedException::class,
                        $target_locked_state ? 'Record already locked' : "Record isn't locked",
                    );
                }

                if ($already_in_target_state || ! method_exists($found_record, $operation)) {
                    continue;
                }

                $found_record->{$operation}();
                $affected_records->add($found_record->fresh());
            }
        });
        throw_if($found_count === 0, ModelNotFoundException::class, 'No model Found');

        return new CrudResult(
            data: $affected_records,
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

        throw_if($this->methodRequiresParameters($model, $method), UnexpectedValueException::class, sprintf('Method %s requires parameters on %s', $method, $model::class));

        return $model->{$method}();
    }

    private function methodRequiresParameters(Model $model, string $method): bool
    {
        return self::$method_requires_parameters_cache[$model::class . '::' . $method]
            ??= (new ReflectionMethod($model, $method))->getNumberOfRequiredParameters() > 0;
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
