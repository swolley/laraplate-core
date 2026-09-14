<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Contracts\ISearchEngine;
use Modules\Core\Search\Traits\Searchable;

/**
 * Searchable model whose engine is injected, for asserting the base
 * Searchable::toSearchableArray() agnostic `embeddings` array against real
 * ModelEmbedding rows (no real search server or Content-sized model needed).
 */
class VectorEmbeddingsArrayStubModel extends Model
{
    use Searchable;

    public $timestamps = false;

    protected $table = 'core_test_vector_embeddings_stub';

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
