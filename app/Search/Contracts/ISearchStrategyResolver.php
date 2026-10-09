<?php

declare(strict_types=1);

namespace Modules\Core\Search\Contracts;

use Modules\Core\Search\DTOs\SearchStrategy;
use Modules\Core\Search\Enums\SearchMode;

/**
 * Decides, per request, which components a search runs with.
 *
 * Core binds a resolver that always returns its cheap implementations; a module that can do more replaces the
 * binding. Core never names the module: the container answers who implements this.
 */
interface ISearchStrategyResolver
{
    /**
     * Never throws because a component is unavailable: it returns the cheap strategy with a `degraded_reason`.
     */
    public function resolve(SearchMode $mode): SearchStrategy;
}
