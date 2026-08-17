<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Modules\Core\Services\Crud\DTOs\FacetQuery;
use Modules\Core\Services\Crud\DTOs\FacetSort;
use Override;

/**
 * Standalone facets request.
 *
 * Extends {@see ListRequest} to reuse the whole list vocabulary (columns, filters,
 * relations, sort, pagination) without touching it. Two shapes are supported on
 * the same endpoint:
 *  - no `facet` payload: every requested `columns` entry is a flat, enumerable
 *    facet dimension (tier 1);
 *  - a singular `facet` object ({@see FacetQuery}): one open, high-cardinality
 *    facet paginated/searched/sorted on its own (tier 2).
 */
final class FacetsRequest extends ListRequest
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function rules(): array
    {
        return parent::rules() + [
            'facet' => ['sometimes', 'array'],
            'facet.groupBy' => ['required_with:facet', 'string'],
            'facet.fields' => ['sometimes', 'array'],
            'facet.fields.*' => ['string'],
            'facet.page' => ['sometimes', 'integer', 'min:1'],
            'facet.perPage' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'facet.search' => ['sometimes', 'nullable', 'string'],
            'facet.sort' => ['sometimes', 'in:' . implode(',', FacetSort::values())],
            'facet.labelField' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * The single open-facet query, when one was requested; null for the flat
     * multi-facet counts.
     */
    public function facet(): ?FacetQuery
    {
        $facet = $this->validated('facet');

        if (! is_array($facet) || (string) ($facet['groupBy'] ?? '') === '') {
            return null;
        }

        return FacetQuery::fromArray($facet);
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $facet = $this->input('facet');

        if (is_string($facet) && is_json($facet)) {
            /** @phpstan-ignore method.notFound */
            $this->merge(['facet' => json_decode($facet, true)]);
        }
    }
}
