<?php

declare(strict_types=1);

use Modules\Core\Import\Enums\OnMissingRelation;
use Modules\Core\Import\Exceptions\RowImportException;
use Modules\Core\Import\Support\RelationValueResolver;
use Modules\Core\Import\ValueObjects\ImportRelationField;

/**
 * @param  array<string, int>  $known
 */
function relationField(OnMissingRelation $onMissing, bool $multiple = true, string $separator = ','): ImportRelationField
{
    return new ImportRelationField('tags', 'Tags', 'tags', multiple: $multiple, separator: $separator, onMissing: $onMissing);
}

function relationFind(array $known): callable
{
    return static fn (string $value): ?int => $known[$value] ?? null;
}

test('it splits a multi-value cell, trims and de-duplicates tokens', function (): void {
    $ids = (new RelationValueResolver)->resolve(
        ' sport , music , sport ',
        relationField(OnMissingRelation::Error),
        relationFind(['sport' => 1, 'music' => 2]),
    );

    expect($ids)->toBe([1, 2]);
});

test('it keeps a comma-bearing single value whole when the field is not multiple', function (): void {
    $ids = (new RelationValueResolver)->resolve(
        'Doe, John',
        relationField(OnMissingRelation::Error, multiple: false),
        relationFind(['Doe, John' => 7]),
    );

    expect($ids)->toBe([7]);
});

test('an empty or null cell resolves to no ids', function (): void {
    $resolver = new RelationValueResolver;
    $field = relationField(OnMissingRelation::Error);

    expect($resolver->resolve(null, $field, relationFind([])))->toBe([])
        ->and($resolver->resolve('   ', $field, relationFind([])))->toBe([]);
});

test('the create policy creates a missing token through the create callback', function (): void {
    $created = [];

    $ids = (new RelationValueResolver)->resolve(
        'sport,news',
        relationField(OnMissingRelation::Create),
        relationFind(['sport' => 1]),
        function (string $value) use (&$created): int {
            $created[] = $value;

            return 99;
        },
    );

    expect($ids)->toBe([1, 99])
        ->and($created)->toBe(['news']);
});

test('the skip policy silently drops a missing token', function (): void {
    $ids = (new RelationValueResolver)->resolve(
        'sport,ghost',
        relationField(OnMissingRelation::Skip),
        relationFind(['sport' => 1]),
    );

    expect($ids)->toBe([1]);
});

test('the error policy fails the row and lists every missing token under the field name', function (): void {
    $call = fn (): array => (new RelationValueResolver)->resolve(
        'sport,ghost,phantom',
        relationField(OnMissingRelation::Error),
        relationFind(['sport' => 1]),
    );

    expect($call)->toThrow(RowImportException::class);

    try {
        $call();
    } catch (RowImportException $exception) {
        expect($exception->errors())->toHaveKey('tags')
            ->and($exception->errors()['tags'][0])->toContain('ghost')
            ->and($exception->errors()['tags'][0])->toContain('phantom');
    }
});

test('the create policy without a create callback is a programming error', function (): void {
    $call = fn (): array => (new RelationValueResolver)->resolve(
        'ghost',
        relationField(OnMissingRelation::Create),
        relationFind([]),
    );

    expect($call)->toThrow(LogicException::class);
});
