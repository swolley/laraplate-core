<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

use Laravel\Scout\Builder;
use Modules\Core\Search\DTOs\ResolvedTextMatch;
use Modules\Core\Search\DTOs\SearchQueryAnalysis;
use Modules\Core\Search\DTOs\TextMatchOptions;
use Modules\Core\Search\Enums\TextMatchPreference;

final readonly class TextMatchOptionsResolver
{
    public const string BUILDER_OPTION = 'text_match';

    public function __construct(
        private SearchQueryAnalyzer $analyzer,
        private SearchQuerySyntaxParser $syntaxParser = new SearchQuerySyntaxParser(),
    ) {}

    public function forBuilder(Builder $builder): TextMatchOptions
    {
        $input = $builder->options[self::BUILDER_OPTION] ?? [];
        $input = is_array($input) ? $input : [];
        $preference = is_string($input['preference'] ?? null) ? $input['preference'] : null;
        unset($input['preference']);

        return $this->resolve($builder->query, $preference, $input)->options;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function resolve(string $query, TextMatchPreference|string|null $preference = null, array $overrides = []): ResolvedTextMatch
    {
        $requested = $preference instanceof TextMatchPreference
            ? $preference
            : TextMatchPreference::tryFrom(is_string($preference) ? $preference : '') ?? TextMatchPreference::Auto;
        $defaults = $this->configArray('search.text_matching.defaults');
        $minimum_length = is_numeric($defaults['minimum_term_length'] ?? null)
            ? (int) $defaults['minimum_term_length']
            : 4;
        $parsed_query = $this->syntaxParser->parse($query);
        $analysis = $this->analyzer->analyze($parsed_query->freeText, $minimum_length);
        $effective = $this->effectivePreference($analysis, $requested);
        $preset = $this->configArray('search.text_matching.preferences.' . $effective->value);
        $adaptive = $this->adaptiveOptions($analysis, $requested, $effective);
        $merged = [
            ...$defaults,
            ...$preset,
            ...$adaptive,
            ...$overrides,
            'query' => $parsed_query->freeText,
            'required_terms' => $parsed_query->requiredTerms,
            'required_phrases' => $parsed_query->requiredPhrases,
        ];

        $contains_uuid = in_array('uuid', $analysis->tokenKinds(), true);

        if ($analysis->protectedTokenCount > 0) {
            // Portable engines cannot all express per-token required clauses, so mixed
            // identifier queries conservatively require complete token coverage.
            $merged['operator'] = 'and';
            $merged['minimum_should_match'] = 100;
        }

        if (($analysis->protectedTokenCount > 0 && ($merged['identifier_typos'] ?? false) !== true) || $contains_uuid) {
            $merged['typo_tolerance'] = false;
            $merged['max_edits'] = 0;
            $merged['fuzzy_token_limit'] = 0;
        } elseif (($merged['identifier_typos'] ?? false) !== true) {
            $merged['fuzzy_token_limit'] = min(
                is_numeric($merged['fuzzy_token_limit'] ?? null) ? (int) $merged['fuzzy_token_limit'] : 0,
                $analysis->eligibleTokenCount,
            );
        }

        if (($merged['typo_tolerance'] ?? true) === false) {
            $merged['max_edits'] = 0;
            $merged['fuzzy_token_limit'] = 0;
        }

        return new ResolvedTextMatch(
            requestedPreference: $requested,
            effectivePreference: $effective,
            analysis: $analysis,
            options: TextMatchOptions::fromArray($merged),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function effectivePreference(SearchQueryAnalysis $analysis, TextMatchPreference $requested): TextMatchPreference
    {
        if ($requested !== TextMatchPreference::Auto) {
            return $requested;
        }

        return $analysis->significantTokenCount === 0 || $analysis->protectedOnly()
            ? TextMatchPreference::Strict
            : TextMatchPreference::Balanced;
    }

    /**
     * @return array<string, mixed>
     */
    private function adaptiveOptions(
        SearchQueryAnalysis $analysis,
        TextMatchPreference $requested,
        TextMatchPreference $effective,
    ): array {
        $significant_tokens = $analysis->significantTokenCount;

        if ($significant_tokens === 0) {
            return [
                'typo_tolerance' => false,
                'max_edits' => 0,
                'operator' => 'and',
                'minimum_should_match' => 100,
                'fuzzy_token_limit' => 0,
            ];
        }

        if ($effective === TextMatchPreference::Strict) {
            return [
                'typo_tolerance' => true,
                'max_edits' => 1,
                'operator' => 'and',
                'minimum_should_match' => 100,
                'fuzzy_token_limit' => 1,
            ];
        }

        $natural_language = count($analysis->tokens) - $significant_tokens >= 2;
        $minimum_should_match = $this->minimumShouldMatch(
            $significant_tokens,
            $requested,
            $natural_language,
        );
        $fuzzy_token_limit = match ($requested) {
            TextMatchPreference::Tolerant => 3,
            TextMatchPreference::Balanced => 2,
            TextMatchPreference::Auto => $natural_language && $significant_tokens >= 5 ? 2 : 1,
            TextMatchPreference::Strict => 1,
        };

        return [
            'typo_tolerance' => true,
            'max_edits' => 1,
            'operator' => $minimum_should_match === 100 ? 'and' : 'or',
            'minimum_should_match' => $minimum_should_match,
            'fuzzy_token_limit' => min($fuzzy_token_limit, $significant_tokens),
        ];
    }

    private function minimumShouldMatch(
        int $significantTokens,
        TextMatchPreference $requested,
        bool $naturalLanguage,
    ): int {
        if ($significantTokens <= 2) {
            return 100;
        }

        if ($requested === TextMatchPreference::Tolerant) {
            return match (true) {
                $significantTokens === 3 => 66,
                $significantTokens === 4 => 75,
                $significantTokens <= 8 => 55,
                default => 50,
            };
        }

        if ($requested === TextMatchPreference::Balanced) {
            return match (true) {
                $significantTokens === 3 => 100,
                $significantTokens === 4 => 75,
                $significantTokens <= 8 => 65,
                default => 60,
            };
        }

        return match (true) {
            $significantTokens === 3 => $naturalLanguage ? 66 : 100,
            $significantTokens === 4 => 75,
            $significantTokens <= 8 => $naturalLanguage ? 65 : 70,
            default => $naturalLanguage ? 60 : 65,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function configArray(string $key): array
    {
        $value = config($key, []);

        return is_array($value) ? $value : [];
    }
}
