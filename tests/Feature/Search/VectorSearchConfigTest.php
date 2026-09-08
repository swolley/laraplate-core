<?php

declare(strict_types=1);

it('keeps vector search off by default until the backfill is run', function (): void {
    expect(config('search.vector_search.enabled'))->toBeFalse();
});

it('defaults the vector dimension to the sentence-transformers output length', function (): void {
    // The default embeddings provider (sentence_transformers) emits 512-dim
    // vectors; the engine index mapping must be created at the same size.
    expect(config('search.vector_search.dimension'))->toBe(512);
});
