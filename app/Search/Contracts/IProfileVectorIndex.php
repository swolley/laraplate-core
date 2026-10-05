<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Illuminate\Database\Connection;

/**
 * Manages the per-embedding-profile vector index of the embeddings table on engines that need one
 * (PostgreSQL with pgvector). Every method is meaningful only where {@see self::supports()} is true.
 */
interface IProfileVectorIndex
{
    /**
     * Whether the connection is PostgreSQL with the pgvector extension installed.
     */
    public function supports(Connection $connection): bool;

    /**
     * Creates the partial index of a profile when it does not exist.
     */
    public function ensure(Connection $connection, string $modelKey, int $dimensions, string $similarity): void;

    /**
     * Like {@see self::ensure()}, but runs at most once per key in this process.
     */
    public function ensureOnce(Connection $connection, string $modelKey, int $dimensions, string $similarity): void;

    /**
     * Drops the partial index of a profile when it exists.
     */
    public function drop(Connection $connection, string $modelKey): void;
}
