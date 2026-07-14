<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Integration\Search;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Laravel\Scout\Builder as ScoutBuilder;

class EnsembleSearchPaginatorTestBuilder extends ScoutBuilder
{
    public bool $getCalled = false;

    public ?int $paginatedPerPage = null;

    public ?int $paginatedPage = null;

    /**
     * @param  Collection<int, Model>  $items
     */
    public function __construct(Model $model, string $query, private readonly Collection $items, private readonly int $total)
    {
        parent::__construct($model, $query);
    }

    public function get(): Collection
    {
        $this->getCalled = true;

        return $this->items;
    }

    public function paginate($perPage = null, $pageName = 'page', $page = null): LengthAwarePaginator
    {
        $this->paginatedPerPage = (int) $perPage;
        $this->paginatedPage = (int) ($page ?? 1);

        return new LengthAwarePaginator(
            items: $this->items,
            total: $this->total,
            perPage: (int) $perPage,
            currentPage: $this->paginatedPage,
        );
    }
}
