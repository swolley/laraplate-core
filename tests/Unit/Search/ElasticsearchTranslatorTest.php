<?php

declare(strict_types=1);

use Modules\Core\Search\Schema\FieldDefinition;
use Modules\Core\Search\Schema\FieldType;
use Modules\Core\Search\Schema\IndexType;
use Modules\Core\Search\Schema\SchemaDefinition;
use Modules\Core\Search\Translators\ElasticsearchTranslator;

it('maps a locale object field to per-language analyzers', function (): void {
    $schema = new SchemaDefinition('cms_contents');
    $schema->addField(new FieldDefinition('title', FieldType::Object, [IndexType::Searchable], [
        'locale_properties' => [
            'it' => ['analyzer' => 'italian'],
            'en' => ['analyzer' => 'english'],
        ],
    ]));

    $mapping = (new ElasticsearchTranslator)->translate($schema);
    $title = $mapping['mappings']['properties']['title'];

    expect($title['type'])->toBe('object')
        ->and($title['properties']['it'])->toMatchArray(['type' => 'text', 'analyzer' => 'italian'])
        ->and($title['properties']['en'])->toMatchArray(['type' => 'text', 'analyzer' => 'english']);
});

it('maps a vector-carrying array field to a nested dense_vector', function (): void {
    $schema = new SchemaDefinition('cms_contents');
    $schema->addField(new FieldDefinition('embeddings', FieldType::Array, [IndexType::Searchable, IndexType::Vector], [
        'vector' => ['dimensions' => 384, 'similarity' => 'cosine'],
    ]));

    $mapping = (new ElasticsearchTranslator)->translate($schema);
    $field = $mapping['mappings']['properties']['embeddings'];

    expect($field['type'])->toBe('nested')
        ->and($field['properties']['vector'])->toMatchArray([
            'type' => 'dense_vector', 'dims' => 384, 'index' => true, 'similarity' => 'cosine',
        ]);
});
