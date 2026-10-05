<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Modules\Core\Search\DTOs\RerankResult;

/**
 * A reranker that can say which model scored the pairs, so a search result and an evaluation report can
 * record the model they were measured with. `score()` returns the same scores without the name.
 */
interface IRerankerWithModel extends IReranker
{
    /**
     * @param  list<array{query: string, text: string}>  $pairs
     */
    public function scoreWithModel(array $pairs): RerankResult;
}
