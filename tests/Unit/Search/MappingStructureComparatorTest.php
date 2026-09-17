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
