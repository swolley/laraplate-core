<?php

declare(strict_types=1);

use Modules\Core\Models\ModelEmbedding;
use Modules\Core\Search\Engines\DatabaseEngine;

function pgvector_engine_query(): Illuminate\Database\Eloquent\Builder
{
    $method = new ReflectionMethod(DatabaseEngine::class, 'postgreSQLVectorSearchQuery');

    return $method->invoke(app(DatabaseEngine::class), [0.1, 0.2], new ModelEmbedding, [1, 2]);
}

it('casts the column and filters on the active model like the partial index', function (): void {
    config()->set('core.search.vector.dimensions', 384);
    config()->set('core.search.vector.similarity', 'cosine');
    config()->set('core.search.vector.model', 'prov:model-a');

    $query = pgvector_engine_query();

    expect($query->toSql())
        ->toContain('"embedding"::vector(384) <=> ?::vector')
        ->toContain('"model_key" = \'prov:model-a\'')
        ->not->toContain('"model_key" = ?')
        ->and($query->getBindings())->not->toContain('prov:model-a');
});

it('uses the operator of the configured similarity', function (string $similarity, string $operator): void {
    config()->set('core.search.vector.dimensions', 8);
    config()->set('core.search.vector.similarity', $similarity);
    config()->set('core.search.vector.model', 'prov:model-a');

    expect(pgvector_engine_query()->toSql())->toContain("\"embedding\"::vector(8) {$operator} ?::vector");
})->with([['cosine', '<=>'], ['l2', '<->'], ['ip', '<#>']]);

it('does not filter on a model when none is configured', function (): void {
    config()->set('core.search.vector.dimensions', 8);
    config()->set('core.search.vector.model', '');

    expect(pgvector_engine_query()->toSql())->not->toContain('"model_key"')
        ->toContain('vector_dims("embedding") = 8');
});

it('escapes the model key literal', function (): void {
    config()->set('core.search.vector.dimensions', 8);
    config()->set('core.search.vector.model', "a'b");

    expect(pgvector_engine_query()->toSql())->toContain("\"model_key\" = 'a''b'");
});

it('rejects non-positive or non-numeric dimensions before building SQL', function (mixed $dimensions): void {
    config()->set('core.search.vector.dimensions', $dimensions);

    expect(fn () => pgvector_engine_query())->toThrow(InvalidArgumentException::class);
})->with([0, -3, 'abc', null]);
