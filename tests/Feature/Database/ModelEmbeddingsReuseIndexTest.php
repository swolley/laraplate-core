<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;

it('indexes embeddings by content hash and embedding model for vector reuse', function (): void {
    $indexed_columns = array_map(
        static fn (array $index): array => $index['columns'],
        Schema::getIndexes(CoreTables::ModelEmbeddings->value),
    );

    expect($indexed_columns)->toContain(['content_hash', 'model_key']);
});
