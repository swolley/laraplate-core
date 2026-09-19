<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\ISearchableModel;
use Modules\Core\Search\Traits\Searchable;

/**
 * Searchable model whose engine is a recording double, so the adaptive bulk
 * indexing path can be driven without a running search server.
 */
class BulkSearchableStubModel extends Model implements ISearchableModel
{
    use Searchable;

    public RecordingSearchEngineStub $engine;

    protected $table = 'core_settings';

    protected $guarded = [];

    public function searchableAs(): string
    {
        return 'core_bulk_stub';
    }

    public function searchableUsing(): RecordingSearchEngineStub
    {
        return $this->engine;
    }

    public function withEngine(RecordingSearchEngineStub $engine): static
    {
        $this->engine = $engine;

        return $this;
    }
}
