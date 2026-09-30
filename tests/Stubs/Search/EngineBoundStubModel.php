<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;

/**
 * Model whose `searchableUsing()` returns whatever engine double the test hands it, so
 * {@see \Modules\Core\Search\Services\AdvancedSearchService} can be driven without Scout.
 */
final class EngineBoundStubModel extends Model
{
    public function __construct(private readonly mixed $engine = null)
    {
        parent::__construct();
    }

    public function searchableUsing(): mixed
    {
        return $this->engine;
    }
}
