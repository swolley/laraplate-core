<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Traits\Searchable;

/**
 * Searchable model whose engine is injected, so indexing failures can be
 * exercised without a running search server.
 */
class DegradingSearchableStubModel extends Model
{
    use Searchable;

    public DegradingSearchEngineStub $engine;

    protected $table = 'core_settings';

    protected $guarded = [];

    public function searchableAs(): string
    {
        return 'core_degrading_stub';
    }

    public function searchableUsing(): DegradingSearchEngineStub
    {
        return $this->engine;
    }

    public function withEngine(DegradingSearchEngineStub $engine): static
    {
        $this->engine = $engine;

        return $this;
    }
}
