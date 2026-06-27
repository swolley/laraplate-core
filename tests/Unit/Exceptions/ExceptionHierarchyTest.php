<?php

declare(strict_types=1);

use Modules\Core\Exceptions\AmbiguousModelException;
use Modules\Core\Exceptions\ConfigurationException;
use Modules\Core\Search\Exceptions\ElasticsearchException;
use Modules\Core\Search\Exceptions\EmbeddingsException;
use Modules\Core\Search\Exceptions\MissingSearchSchemaException;
use Modules\Core\Search\Exceptions\ReindexException;
use Modules\Core\Search\Exceptions\SearchCollectionResolutionException;
use Modules\Core\Grids\Exceptions\ConcurrencyException;
use Modules\Core\Locking\Exceptions\AlreadyLockedException;
use Modules\Core\Locking\Exceptions\CannotUnlockException;
use Modules\Core\Locking\Exceptions\LockedModelException;
use Modules\Core\Search\Exceptions\SearchException;
use Modules\Core\Search\Exceptions\UnsupportedSearchEngineException;

test('core search exceptions extend SearchException', function (): void {
    expect(MissingSearchSchemaException::class)
        ->toExtend(SearchException::class)
        ->and(SearchCollectionResolutionException::class)->toExtend(SearchException::class)
        ->and(ReindexException::class)->toExtend(SearchException::class)
        ->and(EmbeddingsException::class)->toExtend(SearchException::class)
        ->and(ElasticsearchException::class)->toExtend(SearchException::class)
        ->and(UnsupportedSearchEngineException::class)->toExtend(SearchException::class);
});

test('configuration exception extends runtime exception', function (): void {
    expect(ConfigurationException::class)->toExtend(RuntimeException::class);
});

test('ambiguous model exception extends logic exception', function (): void {
    expect(AmbiguousModelException::class)->toExtend(LogicException::class);
});

test('locking and grid concurrency exceptions extend runtime exception', function (): void {
    expect(LockedModelException::class)->toExtend(RuntimeException::class)
        ->and(AlreadyLockedException::class)->toExtend(RuntimeException::class)
        ->and(CannotUnlockException::class)->toExtend(RuntimeException::class)
        ->and(ConcurrencyException::class)->toExtend(RuntimeException::class);
});
