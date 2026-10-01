<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\ISearchableModel;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Traits\Searchable;
use Modules\Core\SoftDeletes\SoftDeletes;

/**
 * Searchable, soft-deletable model whose engine is injected, for asserting the
 * type of the `is_deleted` field in the base Searchable::toSearchableArray()
 * payload without a real search server.
 */
class SoftDeletableSearchStubModel extends Model implements ISearchableModel
{
    use Searchable;
    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'core_test_soft_deletable_search_stub';

    protected $guarded = [];

    private ISearchEngine $engine;

    public function searchableUsing(): ISearchEngine
    {
        return $this->engine;
    }

    public function withEngine(ISearchEngine $engine): static
    {
        $this->engine = $engine;

        return $this;
    }
}
