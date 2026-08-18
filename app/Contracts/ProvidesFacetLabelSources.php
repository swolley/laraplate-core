<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\Services\Crud\DTOs\FacetLabelSource;

/**
 * Opt-in contract for models whose open-facet labels live on a related table
 * reachable by a foreign key that has no declared {@see
 * \Illuminate\Database\Eloquent\Relations\BelongsTo} relation (typically because
 * the relation is exposed only through an accessor). The facet engine consults
 * these sources after the native BelongsTo resolution, so a model can label,
 * search and sort a facet by a foreign key alone.
 */
interface ProvidesFacetLabelSources
{
    /**
     * Facet label sources keyed by the alias used in a facet's `fields` /
     * `labelField` (the segment before the dot).
     *
     * @return array<string, FacetLabelSource>
     */
    public function facetLabelSources(): array;
}
