<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Contracts\ISearchable;
use Modules\Core\Search\Schema\FieldDefinition;
use Modules\Core\Search\Schema\FieldType;
use Modules\Core\Search\Schema\IndexType;
use Modules\Core\Search\Schema\SchemaDefinition;
use Modules\Core\Search\Traits\CommonEngineFunctions;
use Modules\Core\Tests\Stubs\Search\NestedVectorStubModel;

it('resolves the nested embeddings.vector path for a schema Array field carrying a vector option', function (): void {
    $engine = new class implements ISearchable
    {
        use CommonEngineFunctions;

        public function sync(string $modelClass, ?int $id = null, ?string $from = null): int
        {
            return 0;
        }

        public function buildSearchFilters(array $filters): array|string
        {
            return [];
        }

        public function getSearchMapping(Model $model): array
        {
            return [];
        }

        public function checkIndex(string|Model $model): bool
        {
            return true;
        }

        public function reindex(string $modelClass): void {}
    };
    $model = new NestedVectorStubModel();

    $resolve = (new ReflectionClass($engine))->getMethod('resolveVectorField');

    expect($resolve->invoke($engine, $model))->toBe('embeddings.vector');
});

it('still resolves a top-level FieldType::Vector schema field to its own name', function (): void {
    $engine = new class implements ISearchable
    {
        use CommonEngineFunctions;

        public function sync(string $modelClass, ?int $id = null, ?string $from = null): int
        {
            return 0;
        }

        public function buildSearchFilters(array $filters): array|string
        {
            return [];
        }

        public function getSearchMapping(Model $model): array
        {
            return [];
        }

        public function checkIndex(string|Model $model): bool
        {
            return true;
        }

        public function reindex(string $modelClass): void {}
    };
    $model = new class extends Model
    {
        public function getSchemaDefinition(): SchemaDefinition
        {
            $schema = new SchemaDefinition('core_test_top_level_vector');
            $schema->addField(new FieldDefinition('embedding', FieldType::Vector, [IndexType::Searchable, IndexType::Vector], [
                'dimensions' => 384,
            ]));

            return $schema;
        }
    };

    $resolve = (new ReflectionClass($engine))->getMethod('resolveVectorField');

    expect($resolve->invoke($engine, $model))->toBe('embedding');
});

it('resolves the nested embeddings.vector path from a mapping array with a nested dense_vector property', function (): void {
    $engine = new class implements ISearchable
    {
        use CommonEngineFunctions;

        public function sync(string $modelClass, ?int $id = null, ?string $from = null): int
        {
            return 0;
        }

        public function buildSearchFilters(array $filters): array|string
        {
            return [];
        }

        public function getSearchMapping(Model $model): array
        {
            return [];
        }

        public function checkIndex(string|Model $model): bool
        {
            return true;
        }

        public function reindex(string $modelClass): void {}
    };
    $model = new class extends Model
    {
        /**
         * @return array<string, mixed>
         */
        public function getSearchMapping(): array
        {
            return [
                'mappings' => [
                    'properties' => [
                        'embeddings' => [
                            'type' => 'nested',
                            'properties' => [
                                'vector' => [
                                    'type' => 'dense_vector',
                                    'dims' => 384,
                                    'index' => true,
                                    'similarity' => 'cosine',
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }
    };

    $resolve = (new ReflectionClass($engine))->getMethod('resolveVectorField');

    expect($resolve->invoke($engine, $model))->toBe('embeddings.vector');
});
