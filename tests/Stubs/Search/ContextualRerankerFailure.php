<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use RuntimeException;

/**
 * A reranker failure that carries its own report context, as Laravel's exception handler reads it.
 */
final class ContextualRerankerFailure extends RuntimeException
{
    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return ['request_id' => 'abc123', 'status' => 503];
    }
}
