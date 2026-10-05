<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

/**
 * A searchable model that knows the text a reranker should read for it.
 *
 * A search hit carries the model's own attributes, which hold no text when it lives elsewhere, as a
 * translated title and body do. Without this the reranker is given an empty text for every hit and
 * can only return the fused order it was handed, whatever model scores the pairs.
 */
interface IProvidesRerankerText
{
    /**
     * The text to rerank for each of the documents with the given keys, in the given language, keyed by
     * document key. A document with no text in that language is left out.
     *
     * Called once per search with every document to rerank, so an implementation loads what it needs for
     * all of them at once instead of once per document.
     *
     * @param  list<int|string>  $keys
     * @return array<int|string, string>
     */
    public static function rerankerTexts(array $keys, string $locale): array;
}
