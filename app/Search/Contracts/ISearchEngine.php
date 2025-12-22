<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

/**
 * Interface that defines methods for search engines
 * Extends ISearchable to include engine-specific functionality.
 */
interface ISearchEngine extends ISearchable // , ISearchAnalytics
{
    //    public array $config {
    //        get;
    //        set;
    //    }

    /**
     * Check if the engine supports vector search.
     */
    public function supportsVectorSearch(): bool;

    /**
     * Create an index.
     *
     * @param  string|Model&Searchable|class-string<Model>  $name
     * @param  array<string,mixed>  $options  Index options
     * @param  bool  $force  Force index creation even if it already exists
     */
    public function createIndex($name, array $options = [], bool $force = false): void;

    public function health(): array;

    public function stats(): array;

    public function getName(): string;
}
