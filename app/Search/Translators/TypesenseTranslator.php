<?php

declare(strict_types=1);

namespace Modules\Core\Search\Translators;

use Modules\Core\Search\Contracts\ISchemaTranslator;
use Modules\Core\Search\Schema\FieldDefinition;
use Modules\Core\Search\Schema\FieldType;
use Modules\Core\Search\Schema\IndexType;
use Modules\Core\Search\Schema\SchemaDefinition;

class TypesenseTranslator implements ISchemaTranslator
{
    public function translate(SchemaDefinition $schema): array
    {
        $collection = [
            'name' => $schema->name,
            'fields' => [],
        ];

        foreach ($schema->getFields() as $field) {
            $collection['fields'][] = $this->translateField($field);
        }

        return $collection;
    }

    public function getEngineName(): string
    {
        return 'typesense';
    }

    private function translateField(FieldDefinition $field): array
    {
        $tsField = [
            'name' => $field->name,
            'type' => $this->getTypesenseType($field->type, $field->options),
        ];

        // Add index-specific options
        if ($field->hasIndexType(IndexType::SEARCHABLE)) {
            $tsField['index'] = true;
        }

        if ($field->hasIndexType(IndexType::FACETABLE)) {
            $tsField['facet'] = true;
        }

        if ($field->hasIndexType(IndexType::SORTABLE)) {
            $tsField['sort'] = true;
        }

        if ($field->hasIndexType(IndexType::VECTOR)) {
            $tsField['embed'] = true;
        }

        return $tsField;
    }

    private function getTypesenseType(FieldType $type, array $options = []): string
    {
        return match ($type) {
            FieldType::TEXT, FieldType::KEYWORD => 'string',
            FieldType::INTEGER => 'int32',
            FieldType::FLOAT => 'float',
            FieldType::BOOLEAN => 'bool',
            FieldType::DATE => 'string', // Typesense uses string for dates
            FieldType::VECTOR => 'float[]',
            FieldType::ARRAY => isset($options['properties']) ? 'object[]' : 'string[]',
            FieldType::OBJECT, FieldType::GEOCODE => 'object',
        };
    }
}
