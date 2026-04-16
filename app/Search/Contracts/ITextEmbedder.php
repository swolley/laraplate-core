<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

/**
 * Contract for generating text embeddings (vector representations).
 *
 * Used by the search pipeline to enable vector/hybrid search strategies.
 */
interface ITextEmbedder
{
    /**
     * Generate an embedding vector for the given text.
     *
     * @return list<float>  Embedding vector
     */
    public function embed(string $text): array;
}
