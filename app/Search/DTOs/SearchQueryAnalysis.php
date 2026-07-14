<?php

declare(strict_types=1);

namespace Modules\Core\Search\DTOs;

final readonly class SearchQueryAnalysis
{
    /**
     * @param  list<AnalyzedSearchToken>  $tokens
     */
    public function __construct(
        public string $query,
        public array $tokens,
        public int $significantTokenCount,
        public int $protectedTokenCount,
        public int $eligibleTokenCount,
    ) {}

    public function protectedOnly(): bool
    {
        return $this->significantTokenCount > 0 && $this->eligibleTokenCount === 0;
    }

    /**
     * @return list<string>
     */
    public function tokenKinds(): array
    {
        return array_values(array_map(
            static fn (AnalyzedSearchToken $token): string => $token->kind->value,
            $this->tokens,
        ));
    }
}
