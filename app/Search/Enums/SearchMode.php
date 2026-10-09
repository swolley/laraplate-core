<?php

declare(strict_types=1);

namespace Modules\Core\Search\Enums;

/**
 * How much a caller is willing to spend on one search.
 *
 * `Fast` runs Core's own cheap path. `Deep` buys whatever an installed module offers on top (LLM planning,
 * embedding, reranking, retries) and degrades to `Fast`, with a stated reason, when that is not possible.
 * Resolve untrusted input with `tryFrom() ?? Fast` so an unknown value never throws.
 */
enum SearchMode: string
{
    case Fast = 'fast';
    case Deep = 'deep';
}
