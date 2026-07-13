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

        $requires_nested = false;

        foreach ($schema->getFields() as $field) {
            $translated = $this->translateField($field);
            $collection['fields'][] = $translated;

            if ($translated['type'] === 'object' || $translated['type'] === 'object[]') {
                $requires_nested = true;
            }
        }

        if ($requires_nested) {
            $collection['enable_nested_fields'] = true;
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

        // Nested fields for object/object[] types
        if (isset($field->options['properties']) && is_array($field->options['properties'])) {
            $tsField['fields'] = [];

            foreach ($field->options['properties'] as $name => $type) {
                $resolvedType = $type instanceof FieldType ? $type : FieldType::fromValue($type);
                $tsField['fields'][] = [
                    'name' => $name,
                    'type' => $this->getTypesenseType($resolvedType),
                ];
            }
        }

        // Add index-specific options
        if ($field->hasIndexType(IndexType::Searchable)) {
            $tsField['index'] = true;
        }

        if ($field->hasIndexType(IndexType::Filterable) || $field->hasIndexType(IndexType::Facetable)) {
            $tsField['facet'] = true;
        }

        if ($field->hasIndexType(IndexType::Sortable)) {
            $tsField['sort'] = true;
        }

        return $tsField;
    }

    private function getTypesenseType(FieldType $type, array $options = []): string
    {
        return match ($type) {
            FieldType::Text, FieldType::Keyword => 'string',
            FieldType::Integer => 'int32',
            FieldType::Float => 'float',
            FieldType::Boolean => 'bool',
            FieldType::Date => 'string', // Typesense uses string for dates
            FieldType::Vector => 'float[]',
            FieldType::Array => isset($options['properties']) ? 'object[]' : 'string[]',
            FieldType::Object, FieldType::Geocode => 'object',
        };
    }
}
