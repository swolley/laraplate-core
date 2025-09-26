<?php

declare(strict_types=1);

namespace Modules\Core\Search\Schema\Translators;

use Modules\Core\Search\Contracts\ISchemaTranslator;
use Modules\Core\Search\Schema\FieldDefinition;
use Modules\Core\Search\Schema\FieldType;
use Modules\Core\Search\Schema\IndexType;
use Modules\Core\Search\Schema\SchemaDefinition;

class ElasticsearchTranslator implements ISchemaTranslator
{
    public function translate(SchemaDefinition $schema): array
    {
        $mapping = [
            'mappings' => [
                'properties' => [],
            ],
        ];

        foreach ($schema->getFields() as $field) {
            $mapping['mappings']['properties'][$field->name] = $this->translateField($field);
        }

        return $mapping;
    }

    public function getEngineName(): string
    {
        return 'elasticsearch';
    }

    private function translateField(FieldDefinition $field): array
    {
        $esField = match ($field->type) {
            FieldType::TEXT => [
                'type' => 'text',
                'analyzer' => $field->options['analyzer'] ?? 'standard',
            ],
            FieldType::KEYWORD => [
                'type' => 'keyword',
            ],
            FieldType::INTEGER => [
                'type' => 'integer',
            ],
            FieldType::FLOAT => [
                'type' => 'float',
            ],
            FieldType::BOOLEAN => [
                'type' => 'boolean',
            ],
            FieldType::DATE => [
                'type' => 'date',
                'format' => $field->options['format'] ?? 'strict_date_optional_time||epoch_millis',
            ],
            FieldType::VECTOR => [
                'type' => 'dense_vector',
                'dims' => $field->options['dimensions'] ?? 1536,
                'index' => true,
                'similarity' => $field->options['similarity'] ?? 'cosine',
            ],
            FieldType::ARRAY => [
                'type' => $field->options['element_type'] ?? 'text',
            ],
            FieldType::OBJECT => [
                'type' => 'object',
                'properties' => $field->options['properties'] ?? [],
            ],
        };

        // Add index-specific options
        if ($field->hasIndexType(IndexType::SORTABLE)) {
            $esField['doc_values'] = true;
        }

        if ($field->hasIndexType(IndexType::FILTERABLE)) {
            $esField['index'] = true;
        }

        return $esField;
    }
}
