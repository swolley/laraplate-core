<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Traits\Searchable;

/**
 * Throwaway searchable model for asserting that ElasticsearchEngine::createIndex
 * pushes the FULL translated mapping (not just the top-level `embedding` field)
 * at index creation: a `title` locale-object with per-language analyzers and an
 * `embeddings` nested `dense_vector` sub-field, shaped exactly like
 * ElasticsearchTranslator::translateField() would produce them.
 */
class FullMappingStubModel extends Model
{
    use Searchable;

    public const string INDEX = 'core_test_full_mapping';

    public function searchableAs(): string
    {
        return self::INDEX;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSearchMapping(): array
    {
        return [
            'mappings' => [
                'properties' => [
                    'title' => [
                        'type' => 'object',
                        'properties' => [
                            'it' => ['type' => 'text', 'analyzer' => 'italian'],
                            'en' => ['type' => 'text', 'analyzer' => 'english'],
                        ],
                    ],
                    'embeddings' => [
                        'type' => 'nested',
                        'properties' => [
                            'vector' => [
                                'type' => 'dense_vector',
                                'dims' => (int) config('search.vector_search.dimension', 384),
                                'index' => true,
                                'similarity' => 'cosine',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
