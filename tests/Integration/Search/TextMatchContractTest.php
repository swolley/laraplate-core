<?php

declare(strict_types=1);

use Modules\Core\Search\DTOs\TextMatchOptions;
use Modules\Core\Search\Engines\DatabaseEngine;
use Modules\Core\Search\Engines\ElasticsearchEngine;
use Modules\Core\Search\Engines\TypesenseEngine;
use Modules\Core\Search\Services\DatabaseTextMatchCompiler;
use Modules\Core\Search\Services\SearchQueryAnalyzer;
use Modules\Core\Search\Services\TextMatchOptionsResolver;

it('normalizes portable text match options without binding callers to named profiles', function (): void {
    $options = TextMatchOptions::fromArray([
        'max_edits' => 9,
        'operator' => 'unsupported',
        'similarity_threshold' => -1,
        'prefix' => false,
    ]);

    expect($options->maxEdits)->toBe(2)
        ->and($options->operator)->toBe('and')
        ->and($options->similarityThreshold)->toBe(0.0)
        ->and($options->prefix)->toBeFalse();
});

it('translates portable text matching to elasticsearch', function (): void {
    $engine = (new ReflectionClass(ElasticsearchEngine::class))->newInstanceWithoutConstructor();
    $query = $engine->buildTextMatchQuery('fattura', TextMatchOptions::fromArray([
        'max_edits' => 1,
        'prefix' => true,
        'prefix_length' => 2,
        'exact_match_boost' => 3,
    ]));

    expect($query['bool']['should'][0]['multi_match'])
        ->toMatchArray([
            'query' => 'fattura',
            'type' => 'bool_prefix',
            'operator' => 'and',
            'fuzziness' => 1,
            'prefix_length' => 2,
            'fuzzy_transpositions' => true,
        ])
        ->and($query['bool']['should'][1]['multi_match']['boost'])->toBe(3.0);
});

it('does not enable elasticsearch fuzziness below the configured minimum length', function (): void {
    $engine = (new ReflectionClass(ElasticsearchEngine::class))->newInstanceWithoutConstructor();
    $query = $engine->buildTextMatchQuery('fat', new TextMatchOptions());

    expect($query['bool']['should'][0]['multi_match'])->not->toHaveKey('fuzziness');
});

it('translates portable text matching to typesense', function (): void {
    $engine = (new ReflectionClass(TypesenseEngine::class))->newInstanceWithoutConstructor();
    $parameters = $engine->buildTextMatchParameters(TextMatchOptions::fromArray([
        'max_edits' => 2,
        'minimum_term_length' => 5,
        'two_edit_minimum_term_length' => 9,
        'prefix' => false,
    ]));

    expect($parameters)->toMatchArray([
        'num_typos' => 2,
        'prefix' => false,
        'min_len_1typo' => 5,
        'min_len_2typo' => 9,
        'prioritize_exact_match' => true,
    ]);
});

it('compiles a portable database fallback for every database driver', function (string $driver): void {
    $compiled = (new DatabaseTextMatchCompiler())->compile(
        $driver,
        '"documents"."title"',
        'fattura',
        new TextMatchOptions(),
    );

    expect($compiled['sql'])->toBe('LOWER("documents"."title") LIKE LOWER(?)')
        ->and($compiled['bindings'])->toBe(['fattura%'])
        ->and($compiled['degraded'])->toBeTrue();
})->with(['mysql', 'mariadb', 'sqlite', 'oracle']);

it('compiles postgres trigram matching when typo tolerance is available', function (): void {
    $compiled = (new DatabaseTextMatchCompiler())->compile(
        'pgsql',
        '"documents"."title"',
        'fattura',
        TextMatchOptions::fromArray(['similarity_threshold' => 0.7]),
    );

    expect($compiled['sql'])
        ->toContain('strict_word_similarity')
        ->and($compiled['bindings'])->toBe(['fattura%', 'fattura', 0.7])
        ->and($compiled['degraded'])->toBeFalse();
});

it('publishes text match capabilities for every core engine', function (string $engine): void {
    $instance = (new ReflectionClass($engine))->newInstanceWithoutConstructor();

    expect($instance->textMatchCapabilities())->not->toBeEmpty();
})->with([
    ElasticsearchEngine::class,
    TypesenseEngine::class,
    DatabaseEngine::class,
]);

it('classifies names acronyms and identifiers without losing original query signals', function (): void {
    $analysis = (new SearchQueryAnalyzer())->analyze('Mario Rossi CRM INV-1042 mario@example.it 2026');

    expect($analysis->tokenKinds())->toBe([
        'word',
        'word',
        'acronym',
        'structured_identifier',
        'email',
        'numeric',
    ])->and($analysis->significantTokenCount)->toBe(6)
        ->and($analysis->protectedTokenCount)->toBe(4)
        ->and($analysis->eligibleTokenCount)->toBe(2);
});

it('keeps protected-only short queries strict in automatic and tolerant modes', function (string $preference): void {
    $resolved = (new TextMatchOptionsResolver(new SearchQueryAnalyzer()))->resolve('ACME', $preference);

    expect($resolved->options->typoTolerance)->toBeFalse()
        ->and($resolved->options->maxEdits)->toBe(0)
        ->and($resolved->options->fuzzyTokenLimit)->toBe(0);
})->with(['auto', 'tolerant']);

it('allows explicit identifier typo opt in', function (): void {
    $resolved = (new TextMatchOptionsResolver(new SearchQueryAnalyzer()))->resolve('INV-1042', 'tolerant', [
        'identifier_typos' => true,
        'max_edits' => 2,
    ]);

    expect($resolved->options->typoTolerance)->toBeTrue()
        ->and($resolved->options->identifierTypos)->toBeTrue()
        ->and($resolved->options->maxEdits)->toBe(2);
});

it('uses strict two-token matching and relaxes token coverage for longer queries', function (): void {
    $resolver = new TextMatchOptionsResolver(new SearchQueryAnalyzer());
    $name = $resolver->resolve('Mario Rossi');
    $medium = $resolver->resolve('fatture fornitori italiane scadute giugno');
    $long = $resolver->resolve('mostra fatture fornitori italiani scadute giugno anno corrente');

    expect($name->options->operator)->toBe('and')
        ->and($name->options->minimumShouldMatch)->toBe(100)
        ->and($name->options->fuzzyTokenLimit)->toBe(1)
        ->and($medium->options->operator)->toBe('or')
        ->and($medium->options->minimumShouldMatch)->toBe(75)
        ->and($long->options->minimumShouldMatch)->toBe(65);
});

it('lets granular caller options override the preference within portable bounds', function (): void {
    $resolved = (new TextMatchOptionsResolver(new SearchQueryAnalyzer()))->resolve('fattura giugno', 'balanced', [
        'max_edits' => 2,
        'prefix' => false,
        'minimum_should_match' => 80,
    ]);

    expect($resolved->options->maxEdits)->toBe(2)
        ->and($resolved->options->prefix)->toBeFalse()
        ->and($resolved->options->minimumShouldMatch)->toBe(80);
});

it('keeps explicit strict non fuzzy for ordinary unicode names', function (): void {
    $resolved = (new TextMatchOptionsResolver(new SearchQueryAnalyzer()))->resolve('Giuseppe D’Angiò', 'strict');

    expect($resolved->options->typoTolerance)->toBeFalse()
        ->and($resolved->options->maxEdits)->toBe(0)
        ->and($resolved->options->fuzzyTokenLimit)->toBe(0);
});

it('resolves empty and stopword-only queries conservatively', function (string $query): void {
    $resolved = (new TextMatchOptionsResolver(new SearchQueryAnalyzer()))->resolve($query);

    expect($resolved->effectivePreference->value)->toBe('strict')
        ->and($resolved->options->typoTolerance)->toBeFalse();
})->with(['', 'il di e']);
