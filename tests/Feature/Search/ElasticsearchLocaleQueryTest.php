<?php

declare(strict_types=1);

use Laravel\Scout\Builder as ScoutBuilder;
use Modules\Core\Search\Engines\ElasticsearchEngine;
use Modules\Core\Search\Engines\ElasticsearchLocaleTextFields;
use Modules\Core\Search\Services\TextMatchOptionsResolver;
use Modules\Core\Tests\Stubs\Search\FlatTitleStubModel;
use Modules\Core\Tests\Stubs\Search\NestedVectorLocaleStubModel;

/**
 * The vectors carry no language and are searched whole; the language the results are requested in is a
 * document-level restriction (`locales`) and, for the text, a restriction of the fields to that language.
 * The restriction has to be part of the engine query: applied after the top results are fetched it leaves
 * the documents of the wrong language to fill the first places, and the list comes back short.
 */
function es_locale_engine(): ElasticsearchEngine
{
    return (new ReflectionClass(ElasticsearchEngine::class))->newInstanceWithoutConstructor();
}

function es_locale_builder(string $query, ?array $locales = null, ?array $vector = null): ScoutBuilder
{
    $builder = new ScoutBuilder(new NestedVectorLocaleStubModel, $query);
    // Isolate the multi_match under test: skip the exact-match-boost bool/should wrapping.
    $builder->options[TextMatchOptionsResolver::BUILDER_OPTION] = ['exact_match_boost' => 0];

    if ($locales !== null) {
        $builder->where('locales', $locales);
    }

    if ($vector !== null) {
        $builder->where('vector', $vector);
    }

    return $builder;
}

beforeEach(function (): void {
    app()->instance(ElasticsearchLocaleTextFields::class, new ElasticsearchLocaleTextFields(static fn (): array => [
        'title' => ['type' => 'object', 'properties' => ['en' => ['type' => 'text'], 'it' => ['type' => 'text']]],
        'subtitle' => ['type' => 'object', 'properties' => ['en' => ['type' => 'text'], 'it' => ['type' => 'text']]],
        'entity' => ['type' => 'keyword'],
        'locales' => ['type' => 'keyword'],
    ]));
});

it('tells which models can be filtered by language: the ones whose index has a `locales` field', function (): void {
    expect(es_locale_engine()->filtersByLocale(new NestedVectorLocaleStubModel))->toBeTrue()
        ->and(es_locale_engine()->filtersByLocale(new FlatTitleStubModel))->toBeFalse();
});

it('restricts a keyword search to the documents of the requested language and to its text fields', function (): void {
    $params = es_locale_engine()->buildKeywordSearchParams(es_locale_builder('festival', ['it']), 5, 1);
    $bool = $params['body']['query']['bool'];

    expect($bool['filter'])->toContain(['terms' => ['locales' => ['it']]])
        ->and($bool['must'][0]['multi_match']['fields'])->toBe(['title.it^2', 'subtitle.it', 'entity']);
});

it('leaves a keyword search over every language when none is requested', function (): void {
    $params = es_locale_engine()->buildKeywordSearchParams(es_locale_builder('festival'), 5, 1);
    $bool = $params['body']['query']['bool'];

    expect($bool['filter'] ?? [])->toBe([])
        ->and($bool['must'][0]['multi_match']['fields'])->toBe(['title.*^2', '*']);
});

it('restricts a match-all search by language without naming any text field', function (): void {
    $params = es_locale_engine()->buildKeywordSearchParams(es_locale_builder('*', ['it']), 5, 1);
    $bool = $params['body']['query']['bool'];

    expect($bool['filter'])->toContain(['terms' => ['locales' => ['it']]])
        ->and($bool['must'][0])->toHaveKey('match_all');
});

it('keeps the explicit fields a caller asked for', function (): void {
    $builder = es_locale_builder('festival', ['it']);
    $builder->options[TextMatchOptionsResolver::BUILDER_OPTION] = ['exact_match_boost' => 0, 'fields' => ['title.it']];

    $params = es_locale_engine()->buildKeywordSearchParams($builder, 5, 1);

    expect($params['body']['query']['bool']['must'][0]['multi_match']['fields'])->toBe(['title.it']);
});

it('restricts the nearest vectors to the documents of the requested language', function (): void {
    $params = es_locale_engine()->buildVectorSearchParams(es_locale_builder('*', ['it'], [0.1, 0.2]));

    expect($params['body']['knn']['filter']['bool']['must'])->toContain(['terms' => ['locales' => ['it']]])
        ->and($params['body'])->not->toHaveKey('query');
});

it('restricts the text half of a hybrid search too, because its hits are added to the vector ones', function (): void {
    $params = es_locale_engine()->buildVectorSearchParams(es_locale_builder('festival', ['it'], [0.1, 0.2]));
    $text = $params['body']['query']['bool'];

    expect($params['body']['knn']['filter']['bool']['must'])->toContain(['terms' => ['locales' => ['it']]])
        ->and($text['must'][0]['multi_match']['fields'])->toBe(['title.it^2', 'subtitle.it', 'entity'])
        ->and($text['filter'])->toContain(['terms' => ['locales' => ['it']]])
        ->and($text)->not->toHaveKey('should');
});

it('leaves the hybrid text query as it was when no language is requested', function (): void {
    $params = es_locale_engine()->buildVectorSearchParams(es_locale_builder('festival', null, [0.1, 0.2]));

    expect($params['body']['query']['bool']['should'][0]['multi_match']['fields'])->toBe(['title.*^2', '*'])
        ->and($params['body']['knn'])->not->toHaveKey('filter');
});

it('gives no search body for a vector search without a vector', function (): void {
    expect(es_locale_engine()->buildVectorSearchParams(es_locale_builder('festival', ['it'])))->toBeNull();
});

it('applies to the text half of a hybrid search the same filters as to the vectors, whatever they restrict', function (): void {
    $builder = es_locale_builder('festival', null, [0.1, 0.2]);
    $builder->where('entity', 'contents');

    $params = es_locale_engine()->buildVectorSearchParams($builder);
    $vector_filters = $params['body']['knn']['filter']['bool']['must'];
    $text = $params['body']['query']['bool'];

    // The hits of the text half are added to the nearest vectors: with no filter of its own it let a
    // document that matched the text but not the filters come back through the hybrid strategy.
    expect($vector_filters)->not->toBe([])
        ->and($text['filter'])->toBe($vector_filters)
        ->and($text)->toHaveKey('must')
        ->and($text)->not->toHaveKey('should');
});
