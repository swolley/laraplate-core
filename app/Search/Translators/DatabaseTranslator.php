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
        return match ($field->type) {
            FieldType::TEXT, FieldType::KEYWORD => [
                'type' => 'text',
                'nullable' => ! $field->required,
            ],
            FieldType::INTEGER => [
                'type' => 'integer',
                'nullable' => ! $field->required,
            ],
            FieldType::FLOAT => [
                'type' => 'decimal',
                'precision' => 10,
                'scale' => 6,
                'nullable' => ! $field->required,
            ],
            FieldType::BOOLEAN => [
                'type' => 'boolean',
                'nullable' => ! $field->required,
                'default' => $field->default ?? false,
            ],
            FieldType::DATE => [
                'type' => 'timestamp',
                'nullable' => ! $field->required,
            ],
            FieldType::VECTOR => [
                'type' => 'json', // Store as JSON for vector similarity
                'nullable' => ! $field->required,
            ],
            FieldType::ARRAY => [
                'type' => 'json',
                'nullable' => ! $field->required,
            ],
            FieldType::OBJECT => [
                'type' => 'json',
                'nullable' => ! $field->required,
            ],
        };
    }

    private function getIndexes(SchemaDefinition $schema): array
    {
        $indexes = [];

        foreach ($schema->getFields() as $field) {
            if ($field->hasIndexType(IndexType::SEARCHABLE) || $field->hasIndexType(IndexType::FILTERABLE)) {
                $indexes[] = [
                    'name' => "idx_{$schema->name}_{$field->name}",
                    'columns' => [$field->name],
                    'type' => $field->type === FieldType::TEXT ? 'fulltext' : 'btree',
                ];
            }

            if ($field->hasIndexType(IndexType::VECTOR)) {
                $indexes[] = [
                    'name' => "idx_{$schema->name}_{$field->name}_vector",
                    'columns' => [$field->name],
                    'type' => 'vector', // For vector similarity search
                ];
            }
        }

        return $indexes;
    }
}
