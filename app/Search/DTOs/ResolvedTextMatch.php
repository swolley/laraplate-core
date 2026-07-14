<?php

declare(strict_types=1);

namespace Modules\Core\Search\DTOs;

use Modules\Core\Search\Enums\TextMatchPreference;

final readonly class ResolvedTextMatch
{
    public function __construct(
        public TextMatchPreference $requestedPreference,
        public TextMatchPreference $effectivePreference,
        public SearchQueryAnalysis $analysis,
        public TextMatchOptions $options,
    ) {}

    /**
     * @param  list<string>  $degraded
     * @return array<string, mixed>
     */
    public function toMeta(array $degraded = []): array
    {
        return [
            'requested_preference' => $this->requestedPreference->value,
            'effective_preference' => $this->effectivePreference->value,
            'significant_token_count' => $this->analysis->significantTokenCount,
            'token_kinds' => $this->analysis->tokenKinds(),
            'protected_token_count' => $this->analysis->protectedTokenCount,
            'eligible_token_count' => $this->analysis->eligibleTokenCount,
            'fuzzy_token_limit' => $this->options->fuzzyTokenLimit,
            'options' => $this->options->toArray(),
            'degraded' => $degraded,
        ];
    }
}
