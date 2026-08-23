<?php

declare(strict_types=1);

use Modules\Core\Import\Enums\OnMissingRelation;
use Modules\Core\Import\ValueObjects\ImportField;
use Modules\Core\Import\ValueObjects\ImportRelationField;

test('a plain field serializes without a relation block', function (): void {
    expect((new ImportField('name', 'Name', required: true))->toArray())
        ->toBe(['name' => 'name', 'label' => 'Name', 'required' => true]);
});

test('a relation field surfaces its metadata through toField for the mapping UI', function (): void {
    $field = new ImportRelationField('categories', 'Categories', 'categories', onMissing: OnMissingRelation::Error, aliases: ['sections']);

    $serialized = $field->toField()->toArray();

    expect($serialized)->toBe([
        'name' => 'categories',
        'label' => 'Categories',
        'required' => false,
        'relation' => ['multiple' => true, 'separator' => ',', 'on_missing' => 'error'],
    ]);
});

test('the relation descriptor carries the create policy and custom separator', function (): void {
    $field = new ImportRelationField('tags', 'Tags', 'tags', separator: '|', onMissing: OnMissingRelation::Create);

    expect($field->toField()->toArray()['relation'])
        ->toBe(['multiple' => true, 'separator' => '|', 'on_missing' => 'create']);
});
