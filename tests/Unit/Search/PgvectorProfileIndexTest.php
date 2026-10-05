<?php

declare(strict_types=1);

use Modules\Core\Search\Support\PgvectorProfileIndex;

const PGV_KEY = 'sentence_transformers:all-MiniLM-L6-v2';

it('builds a partial hnsw expression index on the cast column', function (): void {
    $statements = PgvectorProfileIndex::statements('core_model_embeddings', PGV_KEY, 384, 'cosine');

    expect($statements)->toHaveCount(1)
        ->and($statements[0])->toStartWith('CREATE INDEX IF NOT EXISTS "me_embedding_')
        ->and($statements[0])->toContain('ON "core_model_embeddings" USING hnsw (("embedding"::vector(384)) vector_cosine_ops)')
        ->and($statements[0])->toEndWith("WHERE (\"model_key\" = '" . PGV_KEY . "')");
});

it('picks the operator class of the similarity', function (string $similarity, string $ops): void {
    expect(PgvectorProfileIndex::statements('t', PGV_KEY, 8, $similarity)[0])->toContain($ops . ')');
})->with([
    'cosine' => ['cosine', 'vector_cosine_ops'],
    'l2' => ['l2', 'vector_l2_ops'],
    'ip' => ['ip', 'vector_ip_ops'],
]);

it('rejects an unknown similarity and invalid dimensions', function (): void {
    expect(fn () => PgvectorProfileIndex::statements('t', PGV_KEY, 8, 'manhattan'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PgvectorProfileIndex::statements('t', PGV_KEY, 0, 'cosine'))->toThrow(InvalidArgumentException::class);
});

it('names the index from a hash of the key, within the identifier limit and distinct per key', function (): void {
    $odd = "weird:key/with'quote\"and\\slash" . str_repeat('x', 200);

    $names = array_map(PgvectorProfileIndex::indexName(...), [PGV_KEY, 'other:model', $odd]);

    expect(array_unique($names))->toHaveCount(3)
        ->and(PgvectorProfileIndex::indexName(PGV_KEY))->toBe($names[0]);

    foreach ($names as $name) {
        expect(mb_strlen($name))->toBeLessThanOrEqual(63)->and($name)->toMatch('/^[a-z0-9_]+$/');
    }
});

it('escapes quotes of the key in the predicate', function (): void {
    $sql = PgvectorProfileIndex::statements('t', "a'b\"c:d/e", 8, 'cosine')[0];

    expect($sql)->toContain("WHERE (\"model_key\" = 'a''b\"c:d/e')");
});

it('drops the index it creates for the same key', function (): void {
    $create = PgvectorProfileIndex::statements('t', PGV_KEY, 8, 'cosine')[0];
    $drop = PgvectorProfileIndex::dropStatement(PGV_KEY);

    expect($drop)->toBe('DROP INDEX IF EXISTS "' . PgvectorProfileIndex::indexName(PGV_KEY) . '"')
        ->and($create)->toContain('"' . PgvectorProfileIndex::indexName(PGV_KEY) . '"');
});

it('maps the similarity to the distance operator the index uses', function (string $similarity, string $operator): void {
    expect(PgvectorProfileIndex::distanceOperator($similarity))->toBe($operator);
})->with([['cosine', '<=>'], ['l2', '<->'], ['ip', '<#>']]);

it('runs ensureOnce once per key and ensure again after a drop', function (): void {
    $connection = Mockery::mock(Illuminate\Database\Connection::class);
    $connection->shouldReceive('getTablePrefix')->andReturn('');
    $connection->shouldReceive('selectOne')->andReturn(null);
    $connection->shouldReceive('statement')->times(4);

    $index = new PgvectorProfileIndex;
    $index->ensureOnce($connection, PGV_KEY, 8, 'cosine');
    $index->ensureOnce($connection, PGV_KEY, 8, 'cosine');
    $index->ensureOnce($connection, 'other', 8, 'cosine');
    $index->drop($connection, PGV_KEY);
    $index->ensureOnce($connection, PGV_KEY, 8, 'cosine');
});

it('issues no CREATE INDEX when the index already exists', function (): void {
    $connection = Mockery::mock(Illuminate\Database\Connection::class);
    $connection->shouldReceive('selectOne')->once()->andReturn((object) ['?column?' => 1]);
    $connection->shouldNotReceive('statement');

    $index = new PgvectorProfileIndex;
    $index->ensure($connection, PGV_KEY, 8, 'cosine');
    $index->ensureOnce($connection, PGV_KEY, 8, 'cosine');
});

it('quotes a literal by doubling single quotes', function (): void {
    expect(PgvectorProfileIndex::quoteLiteral("a'b"))->toBe("'a''b'");
});
