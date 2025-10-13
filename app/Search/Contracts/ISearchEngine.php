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

    public function createIndex($name, array $options = []): void;

    public function health(): array;

    public function stats(): array;

    public function getName(): string;
}
