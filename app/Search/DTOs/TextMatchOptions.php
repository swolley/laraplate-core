<?php

declare(strict_types=1);

namespace Modules\Core\Search\DTOs;

final readonly class TextMatchOptions
{
    public function __construct(
        public bool $typoTolerance = true,
        public int $maxEdits = 1,
        public bool $prefix = true,
        public int $prefixLength = 2,
        public int $minimumTermLength = 4,
        public int $twoEditMinimumTermLength = 8,
        public float $exactMatchBoost = 2.0,
        public string $operator = 'and',
        public int $minimumShouldMatch = 100,
        public bool $transpositions = true,
        public float $similarityThreshold = 0.6,
        public int $fuzzyTokenLimit = 1,
        public bool $identifierTypos = false,
        public string $query = '',
        /** @var list<string> */
        public array $requiredTerms = [],
        /** @var list<string> */
        public array $requiredPhrases = [],
    ) {}

    /**
     * @param  array<string, mixed>  $options
     */
    public static function fromArray(array $options): self
    {
        $defaults = new self();

        return new self(
            typoTolerance: self::boolValue($options['typo_tolerance'] ?? null, $defaults->typoTolerance),
            maxEdits: self::boundedInt($options['max_edits'] ?? null, $defaults->maxEdits, 0, 1),
            prefix: self::boolValue($options['prefix'] ?? null, $defaults->prefix),
            prefixLength: self::boundedInt($options['prefix_length'] ?? null, $defaults->prefixLength, 0, 10),
            minimumTermLength: self::boundedInt($options['minimum_term_length'] ?? null, $defaults->minimumTermLength, 1, 50),
            twoEditMinimumTermLength: self::boundedInt($options['two_edit_minimum_term_length'] ?? null, $defaults->twoEditMinimumTermLength, 1, 50),
            exactMatchBoost: self::boundedFloat($options['exact_match_boost'] ?? null, $defaults->exactMatchBoost, 0.0, 100.0),
            operator: ($options['operator'] ?? null) === 'or' ? 'or' : 'and',
            minimumShouldMatch: self::boundedInt($options['minimum_should_match'] ?? null, $defaults->minimumShouldMatch, 1, 100),
            transpositions: self::boolValue($options['transpositions'] ?? null, $defaults->transpositions),
            similarityThreshold: self::boundedFloat($options['similarity_threshold'] ?? null, $defaults->similarityThreshold, 0.0, 1.0),
            fuzzyTokenLimit: self::boundedInt($options['fuzzy_token_limit'] ?? null, $defaults->fuzzyTokenLimit, 0, 100),
            identifierTypos: self::boolValue($options['identifier_typos'] ?? null, $defaults->identifierTypos),
            query: is_string($options['query'] ?? null) ? $options['query'] : '',
            requiredTerms: self::stringList($options['required_terms'] ?? null),
            requiredPhrases: self::stringList($options['required_phrases'] ?? null),
        );
    }

    /**
     * @return array<string, bool|float|int|string>
     */
    public function toArray(): array
    {
        return [
            'typo_tolerance' => $this->typoTolerance,
            'max_edits' => $this->maxEdits,
            'prefix' => $this->prefix,
            'prefix_length' => $this->prefixLength,
            'minimum_term_length' => $this->minimumTermLength,
            'two_edit_minimum_term_length' => $this->twoEditMinimumTermLength,
            'exact_match_boost' => $this->exactMatchBoost,
            'operator' => $this->operator,
            'minimum_should_match' => $this->minimumShouldMatch,
            'transpositions' => $this->transpositions,
            'similarity_threshold' => $this->similarityThreshold,
            'fuzzy_token_limit' => $this->fuzzyTokenLimit,
            'identifier_typos' => $this->identifierTypos,
        ];
    }

    /**
     * Include parsed syntax values for trusted internal engine propagation.
     *
     * @return array<string, bool|float|int|string|list<string>>
     */
    public function toEngineArray(): array
    {
        return [
            ...$this->toArray(),
            'query' => $this->query,
            'required_terms' => $this->requiredTerms,
            'required_phrases' => $this->requiredPhrases,
        ];
    }

    private static function boolValue(mixed $value, bool $default): bool
    {
        return is_bool($value) ? $value : $default;
    }

    private static function boundedInt(mixed $value, int $default, int $minimum, int $maximum): int
    {
        return is_numeric($value) ? max($minimum, min($maximum, (int) $value)) : $default;
    }

    private static function boundedFloat(mixed $value, float $default, float $minimum, float $maximum): float
    {
        return is_numeric($value) ? max($minimum, min($maximum, (float) $value)) : $default;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== ''));
    }
}
