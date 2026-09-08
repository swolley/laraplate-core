<?php

declare(strict_types=1);

it('ships with the search reranker enabled by default', function (): void {
    expect(config('search.features.reranker'))->toBeTrue()
        ->and(config('search.reranker.top_k'))->toBe(30);
});
