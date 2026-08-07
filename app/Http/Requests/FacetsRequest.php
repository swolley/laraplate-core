<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

/**
 * Standalone facets request.
 *
 * Extends {@see ListRequest} to reuse the whole list vocabulary (columns, filters,
 * relations, sort, pagination) without touching it. The facets endpoint interprets
 * every requested `columns` entry as a facet dimension — there is no data list here,
 * so no per-column marker is needed.
 */
final class FacetsRequest extends ListRequest {}
