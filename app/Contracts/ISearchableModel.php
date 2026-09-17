<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * A model that is indexed in the search engine.
 *
 * Supplied by {@see \Modules\Core\Search\Traits\Searchable}, which wraps Scout and
 * adds the index lifecycle this application needs. Generic code — the reindex
 * action on a Filament table, the index-check command, the ensemble search — took
 * an Eloquent Model and called these on faith.
 *
 * Scout's own methods are declared without return types, the way Scout declares
 * them, so a model inheriting them stays compatible.
 */
interface ISearchableModel
{
    public static function reindex(?int $chunk = null): void;

    public function ensureIndexExists(): bool;

    public function isEmbeddable(): bool;

    /**
     * @return list<string>
     */
    public function getEmbedFields(): array;

    /**
     * @phpstan-return \Laravel\Scout\Engines\Engine
     */
    public function searchableUsing();

    /**
     * @phpstan-return string
     */
    public function searchableAs();

    public function searchable();

    public function unsearchable();
}
