<?php

declare(strict_types=1);

namespace Modules\Core\Search\Services;

use Laravel\Scout\Builder;
use Modules\Core\Search\DTOs\ResolvedTextMatch;
use Modules\Core\Search\DTOs\TextMatchOptions;
use Modules\Core\Search\Enums\TextMatchPreference;

final readonly class TextMatchOptionsResolver
{
    public const string BUILDER_OPTION = 'text_match';

    public function __construct(private SearchQueryAnalyzer $analyzer) {}

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
        $analysis = $this->analyzer->analyze($query, $minimum_length);
        $effective = $requested === TextMatchPreference::Auto
            ? ($analysis->significantTokenCount === 0 || $analysis->protectedOnly()
                ? TextMatchPreference::Strict
                : TextMatchPreference::Balanced)
            : $requested;
        $preset = $this->configArray('search.text_matching.preferences.' . $effective->value);
        $adaptive = $this->adaptiveOptions($analysis->significantTokenCount, $effective);
        $merged = [...$defaults, ...$preset, ...$adaptive, ...$overrides];

        if ($analysis->protectedTokenCount > 0 && ($merged['identifier_typos'] ?? false) !== true) {
            $merged['typo_tolerance'] = false;
            $merged['max_edits'] = 0;
            $merged['fuzzy_token_limit'] = 0;
        } elseif (($merged['identifier_typos'] ?? false) !== true) {
            $merged['fuzzy_token_limit'] = min(
                is_numeric($merged['fuzzy_token_limit'] ?? null) ? (int) $merged['fuzzy_token_limit'] : 0,
                $analysis->eligibleTokenCount,
            );
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
    private function adaptiveOptions(int $significantTokens, TextMatchPreference $preference): array
    {
        if ($preference === TextMatchPreference::Strict || $significantTokens === 0) {
            return [
                'typo_tolerance' => false,
                'max_edits' => 0,
                'operator' => 'and',
                'minimum_should_match' => 100,
                'fuzzy_token_limit' => 0,
            ];
        }

        if ($significantTokens === 1) {
            return [
                'max_edits' => $preference === TextMatchPreference::Tolerant ? 2 : 1,
                'operator' => 'and',
                'minimum_should_match' => 100,
                'fuzzy_token_limit' => 1,
            ];
        }

        if ($significantTokens === 2) {
            return [
                'max_edits' => $preference === TextMatchPreference::Tolerant ? 2 : 1,
                'operator' => 'and',
                'minimum_should_match' => 100,
                'fuzzy_token_limit' => 1,
            ];
        }

        if ($significantTokens <= 5) {
            return [
                'max_edits' => $preference === TextMatchPreference::Tolerant ? 2 : 1,
                'operator' => 'or',
                'minimum_should_match' => $preference === TextMatchPreference::Tolerant ? 65 : 75,
                'fuzzy_token_limit' => $significantTokens,
            ];
        }

        return [
            'max_edits' => $preference === TextMatchPreference::Tolerant ? 2 : 1,
            'operator' => 'or',
            'minimum_should_match' => $preference === TextMatchPreference::Tolerant ? 55 : 65,
            'fuzzy_token_limit' => $significantTokens,
        ];
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
