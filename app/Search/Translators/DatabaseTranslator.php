<?php

declare(strict_types=1);

namespace Modules\Core\Search\Translators;

use Modules\Core\Search\Contracts\ISchemaTranslator;
use Modules\Core\Search\Schema\FieldDefinition;
use Modules\Core\Search\Schema\FieldType;
use Modules\Core\Search\Schema\IndexType;
use Modules\Core\Search\Schema\SchemaDefinition;

class DatabaseTranslator implements ISchemaTranslator
{
    public function translate(SchemaDefinition $schema): array
    {
        // For database, we return migration-like structure
        return [
            'table' => $schema->name,
            'columns' => $this->getColumns($schema),
            'indexes' => $this->getIndexes($schema),
        ];
    }

    public function getEngineName(): string
    {
        return 'database';
    }

    private function getColumns(SchemaDefinition $schema): array
    {
        $columns = [];

        foreach ($schema->getFields() as $field) {
            $columns[$field->name] = $this->getColumnDefinition($field);
        }

        return $columns;
    }

    private function getColumnDefinition(FieldDefinition $field): array
    {
        $definition = match ($field->type) {
            FieldType::Text, FieldType::Keyword => [
                'type' => 'text',
                'nullable' => ! $field->required,
            ],
            FieldType::Integer => [
                'type' => 'integer',
                'nullable' => ! $field->required,
            ],
            FieldType::Float => [
                'type' => 'decimal',
                'precision' => 10,
                'scale' => 6,
                'nullable' => ! $field->required,
            ],
            FieldType::Boolean => [
                'type' => 'boolean',
                'nullable' => ! $field->required,
                'default' => $field->default ?? false,
            ],
            FieldType::Date => [
                'type' => 'timestamp',
                'nullable' => ! $field->required,
            ],
            FieldType::Vector => [
                'type' => 'json', // Store as JSON for vector similarity
                'nullable' => ! $field->required,
            ],
            FieldType::Array => [
                'type' => 'json',
                'nullable' => ! $field->required,
            ],
            FieldType::Object => [
                'type' => 'json',
                'nullable' => ! $field->required,
            ],
        };

        $definition['filterable'] = $field->hasIndexType(IndexType::Filterable) || $field->hasIndexType(IndexType::Facetable);

        return $definition;
    }

    private function getIndexes(SchemaDefinition $schema): array
    {
        $indexes = [];

        foreach ($schema->getFields() as $field) {
            if ($field->hasIndexType(IndexType::Searchable) || $field->hasIndexType(IndexType::Filterable)) {
                $indexes[] = [
                    'name' => sprintf('idx_%s_%s', $schema->name, $field->name),
                    'columns' => [$field->name],
                    'type' => $field->type === FieldType::Text ? 'fulltext' : 'btree',
                ];
            }

            if ($field->hasIndexType(IndexType::Vector)) {
                $indexes[] = [
                    'name' => sprintf('idx_%s_%s_vector', $schema->name, $field->name),
                    'columns' => [$field->name],
                    'type' => 'vector', // For vector similarity search
                ];
            }
        }

        return $indexes;
    }
}
