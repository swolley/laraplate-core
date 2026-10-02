<?php

declare(strict_types=1);

use Modules\Core\Search\Engines\ElasticsearchLocaleTextFields;
use Modules\Core\Tests\Stubs\Search\EngineBoundStubModel;
use Modules\Core\Tests\Stubs\Search\FullMappingStubModel;

/**
 * Which fields a text query searches when the results are requested in a language. The vectors carry no
 * language, but the text does: `title.it` is analysed as Italian, `title.en` as English, and a request in
 * Italian must not match words in the English fields. The mapping read is the live one, because the
 * component fields (subtitle, content, ...) are only mapped dynamically, as an object per language.
 *
 * @return array<string, mixed>
 */
function es_live_properties(): array
{
    $per_language = static fn (array $languages): array => [
        'type' => 'object',
        'properties' => array_fill_keys($languages, ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]]),
    ];

    return [
        '_indexed_at' => ['type' => 'date'],
        'id' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]],
        'connection' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]],
        'entity' => ['type' => 'keyword'],
        'preset' => ['type' => 'keyword'],
        'type' => ['type' => 'keyword'],
        'extended_type' => ['type' => 'keyword'],
        'media_surrogate' => ['type' => 'text'],
        'locales' => ['type' => 'keyword'],
        'is_deleted' => ['type' => 'boolean'],
        'valid_from' => ['type' => 'date'],
        'embeddings' => ['type' => 'nested', 'properties' => ['vector' => ['type' => 'dense_vector']]],
        'tags' => ['type' => 'nested', 'properties' => ['name' => ['type' => 'keyword'], 'id' => ['type' => 'integer']]],
        'contributors' => ['type' => 'nested', 'properties' => ['name' => ['type' => 'text']]],
        'extension' => ['type' => 'object', 'properties' => ['code' => ['type' => 'keyword'], 'weight' => ['type' => 'integer']]],
        'title' => [
            'type' => 'object',
            'properties' => ['de' => ['type' => 'text'], 'en' => ['type' => 'text', 'analyzer' => 'english'], 'it' => ['type' => 'text', 'analyzer' => 'italian']],
        ],
        'slug' => ['type' => 'object', 'properties' => ['en' => ['type' => 'text'], 'it' => ['type' => 'text']]],
        'subtitle' => $per_language(['it']),
        'content' => $per_language(['en', 'it']),
        'short_content' => $per_language(['en', 'it']),
        'kicker' => $per_language(['en', 'it']),
    ];
}

function es_text_fields(array $locales, array $properties = []): array
{
    return ElasticsearchLocaleTextFields::fromProperties($properties ?: es_live_properties(), $locales, ['de', 'en', 'es', 'it', 'sl']);
}

it('searches the fields of the requested language, with the title boosted', function (): void {
    $fields = es_text_fields(['it']);

    expect($fields)->toContain('title.it^2')
        ->and($fields)->toContain('slug.it')
        ->and($fields)->toContain('subtitle.it')
        ->and($fields)->toContain('content.it')
        ->and($fields)->toContain('short_content.it')
        ->and($fields)->toContain('kicker.it');
});

it('never searches the fields of another language', function (): void {
    $fields = es_text_fields(['it']);

    foreach (['title.en', 'title.de', 'slug.en', 'content.en', 'short_content.en', 'kicker.en'] as $other) {
        expect($fields)->not->toContain($other)->not->toContain($other . '^2');
    }
});

it('keeps the text and keyword fields that carry no language', function (): void {
    expect(es_text_fields(['it']))
        ->toContain('entity')
        ->toContain('preset')
        ->toContain('type')
        ->toContain('extended_type')
        ->toContain('media_surrogate')
        ->toContain('id')
        ->toContain('connection')
        ->toContain('extension.code');
});

it('leaves out what a text query cannot reach or must not match', function (): void {
    $fields = es_text_fields(['it']);

    // nested fields are invisible to a plain multi_match, dates, numbers, booleans and vectors are not text,
    // and `locales` would make the word "it" match every document available in Italian
    foreach (['locales', 'embeddings.vector', 'tags.name', 'contributors.name', 'is_deleted', 'valid_from', '_indexed_at', 'extension.weight'] as $skipped) {
        expect($fields)->not->toContain($skipped);
    }
});

it('takes the fields of every requested language', function (): void {
    $fields = es_text_fields(['it', 'en']);

    expect($fields)->toContain('title.it^2')
        ->and($fields)->toContain('title.en^2')
        ->and($fields)->toContain('content.it')
        ->and($fields)->toContain('content.en')
        ->and($fields)->not->toContain('title.de');
});

it('skips an object that has no sub-field for the requested language', function (): void {
    $fields = es_text_fields(['en']);

    expect($fields)->not->toContain('subtitle.it')
        ->and($fields)->not->toContain('subtitle.en');
});

it('has no duplicate fields', function (): void {
    $fields = es_text_fields(['it', 'it']);

    expect($fields)->toBe(array_values(array_unique($fields)));
});

it('reads the live mapping first and falls back to the declared one when it cannot be read', function (): void {
    $model = new FullMappingStubModel;

    $live = (new ElasticsearchLocaleTextFields(static fn (): array => es_live_properties()))->forModel($model, 'idx', ['it']);
    $declared = (new ElasticsearchLocaleTextFields(static fn (): null => null))->forModel($model, 'idx', ['it']);

    expect($live)->toContain('content.it')
        ->and($declared)->toContain('title.it^2')
        ->and($declared)->not->toContain('content.it')
        ->and($declared)->not->toContain('title.en');
});

it('gives no fields when no mapping is available, so the query keeps its default fields', function (): void {
    $resolver = new ElasticsearchLocaleTextFields(static fn (): null => null);

    expect($resolver->forModel(new EngineBoundStubModel, 'idx', ['it']))->toBe([]);
});
