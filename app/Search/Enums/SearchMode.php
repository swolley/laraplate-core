<?php

declare(strict_types=1);

namespace Modules\Core\Search\Enums;

/**
 * How much a caller is willing to spend on one search.
 *
 * `Fast` runs Core's own cheap path. `Balanced` adds the query embedding and nothing else. `Deep` buys whatever an
 * installed module offers on top (LLM planning, embedding, reranking, retries). A mode that cannot be served
 * degrades to a cheaper one, with a stated reason.
 * Resolve untrusted input with `tryFrom() ?? Fast` so an unknown value never throws.
 */
enum SearchMode: string
{
    case Fast = 'fast';
    case Balanced = 'balanced';
    case Deep = 'deep';
}
