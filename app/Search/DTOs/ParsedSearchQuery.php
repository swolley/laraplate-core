<?php

declare(strict_types=1);

namespace Modules\Core\Search\DTOs;

final readonly class ParsedSearchQuery
{
    /**
     * @param  list<string>  $requiredTerms
     * @param  list<string>  $requiredPhrases
     */
    public function __construct(
        public string $freeText,
        public array $requiredTerms = [],
        public array $requiredPhrases = [],
    ) {}
}
