<?php

declare(strict_types=1);

namespace Modules\Core\Services\Crud\Aggregate;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;

/**
 * The rows an aggregate may read and the lookups it may make on them, as the CRUD service grants them: every
 * query the engine builds starts from these closures, so the row-level ACL, the list filters and the permission
 * and ACL of a related entity bound every figure.
 *
 * The closures stand for helpers the service keeps private, so the engine stays a class of its own.
 */
final readonly class AggregateScope
{
    /**
     * @param  Closure(): Builder<Model>  $baseQuery  The rows of the entity a list with the same filters shows: the ACL, then the filters.
     * @param  Closure(): BaseQueryBuilder  $filteredIds  The primary keys of those rows, for a `whereIn` that joins nothing into the aggregated query.
     * @param  Closure(Model, string): (BaseQueryBuilder|null)  $visibleRelatedKeys  The values of a column of a related entity on the rows the caller may see there; null when nothing narrows them.
     * @param  Closure(string): (array{related: Model, relatedTable: string, foreignKey: string, ownerKey: string, column: string}|null)  $resolveToOne  The single-hop to-one join of a `relation.column` path; null for any other path.
     * @param  Closure(string, list<mixed>, string): array<mixed, mixed>  $resolveLabels  The label of each given group key, read through a to-one relation column (`customer.name`).
     */
    public function __construct(
        public Model $model,
        public Closure $baseQuery,
        public Closure $filteredIds,
        public Closure $visibleRelatedKeys,
        public Closure $resolveToOne,
        public Closure $resolveLabels,
    ) {}
}
