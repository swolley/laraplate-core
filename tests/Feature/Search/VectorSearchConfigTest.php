<?php

declare(strict_types=1);

use Modules\Core\Database\Seeders\CoreDatabaseSeeder;

it('seeds the vector dimension at the sentence-transformers output length (384)', function (): void {
    // The embeddings provider (sentence_transformers, all-MiniLM-L6-v2) emits
    // 384-dim vectors; the seeded setting is the authoritative source overlaid
    // onto config, so the engine index mapping is created at 384. A mismatch
    // (e.g. the former 768) produces an unusable dense_vector index.
    $definition = collect(CoreDatabaseSeeder::runtimeSettingDefinitions())
        ->firstWhere('name', 'search.vector_search.dimension');

    expect($definition)->not->toBeNull()
        ->and($definition['value'])->toBe(384);
});
