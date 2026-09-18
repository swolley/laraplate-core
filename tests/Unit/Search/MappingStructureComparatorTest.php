<?php

declare(strict_types=1);

use Modules\Core\Search\Schema\MappingStructureComparator;

it('matches when every declared field type equals the live mapping', function (): void {
    $expected = [
        'title' => ['type' => 'object', 'properties' => ['it' => ['type' => 'text'], 'en' => ['type' => 'text']]],
        'embeddings' => ['type' => 'nested', 'properties' => ['vector' => ['type' => 'dense_vector', 'dims' => 384]]],
    ];

    $live = [
        'title' => ['properties' => [
            'it' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword', 'ignore_above' => 256]]],
            'en' => ['type' => 'text'],
        ]],
        'embeddings' => ['type' => 'nested', 'properties' => ['vector' => ['type' => 'dense_vector', 'dims' => 384]]],
        'created_at' => ['type' => 'date'],
    ];

    expect(MappingStructureComparator::matches($expected, $live))->toBeTrue();
});

it('detects a field whose live type drifted from the declared type', function (): void {
    $expected = ['title' => ['type' => 'object', 'properties' => ['it' => ['type' => 'text']]]];
    $live = ['title' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]]];

    expect(MappingStructureComparator::matches($expected, $live))->toBeFalse();
});

it('detects a declared field missing from the live mapping', function (): void {
    $expected = ['title' => ['type' => 'text'], 'embeddings' => ['type' => 'nested']];
    $live = ['title' => ['type' => 'text']];

    expect(MappingStructureComparator::matches($expected, $live))->toBeFalse();
});

it('ignores live-only fields added by dynamic mapping', function (): void {
    $expected = ['title' => ['type' => 'text']];
    $live = ['title' => ['type' => 'text'], 'slug' => ['type' => 'keyword']];

    expect(MappingStructureComparator::matches($expected, $live))->toBeTrue();
});

it('detects a drifted object sub-field type', function (): void {
    $expected = ['title' => ['type' => 'object', 'properties' => ['it' => ['type' => 'text'], 'en' => ['type' => 'text']]]];
    $live = ['title' => ['properties' => ['it' => ['type' => 'text'], 'en' => ['type' => 'keyword']]]];

    expect(MappingStructureComparator::matches($expected, $live))->toBeFalse();
});

it('diff describes a drifted top-level field type', function (): void {
    $expected = ['title' => ['type' => 'object', 'properties' => ['it' => ['type' => 'text']]]];
    $live = ['title' => ['type' => 'text']];

    expect(MappingStructureComparator::diff($expected, $live))
        ->toBe(["field 'title' is 'text' in the live index but the model declares 'object'"]);
});

it('diff reports a missing field and a drifted object sub-field with dotted paths', function (): void {
    $expected = [
        'title' => ['type' => 'object', 'properties' => ['it' => ['type' => 'text'], 'en' => ['type' => 'text']]],
        'embeddings' => ['type' => 'nested'],
    ];
    $live = [
        'title' => ['properties' => ['it' => ['type' => 'text'], 'en' => ['type' => 'keyword']]],
    ];

    expect(MappingStructureComparator::diff($expected, $live))
        ->toContain("field 'title.en' is 'keyword' in the live index but the model declares 'text'")
        ->toContain("field 'embeddings' is declared by the model but missing from the live index");
});

it('diff is empty when the structure matches', function (): void {
    $expected = ['title' => ['type' => 'text']];
    $live = ['title' => ['type' => 'text'], 'extra' => ['type' => 'keyword']];

    expect(MappingStructureComparator::diff($expected, $live))->toBe([]);
});
