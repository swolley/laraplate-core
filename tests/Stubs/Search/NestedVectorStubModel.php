<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Schema\FieldDefinition;
use Modules\Core\Search\Schema\FieldType;
use Modules\Core\Search\Schema\IndexType;
use Modules\Core\Search\Schema\SchemaDefinition;

/**
 * Throwaway model exposing a PUBLIC getSchemaDefinition() with an `embeddings`
 * Array field carrying a `vector` option, for asserting that
 * CommonEngineFunctions::resolveVectorField() resolves it to the nested
 * `embeddings.vector` kNN field path instead of the parent field name.
 */
class NestedVectorStubModel extends Model
{
    public function getSchemaDefinition(): SchemaDefinition
    {
        $schema = new SchemaDefinition('core_test_nested_vector');
        $schema->addField(new FieldDefinition('embeddings', FieldType::Array, [IndexType::Searchable, IndexType::Vector], [
            'vector' => ['dimensions' => 384, 'similarity' => 'cosine'],
        ]));

        return $schema;
    }
}
