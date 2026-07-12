<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Modules\Core\Http\Requests\SearchRequest;

class SearchRequestData extends ListRequestData
{
    public readonly string $qs;

    public readonly SearchMode $mode;

    /**
     * @param  string|array<string>  $primaryKey
     */
    public function __construct(SearchRequest $request, ?string $mainEntity, array $validated, string|array $primaryKey, ?string $module = null)
    {
        parent::__construct($request, $mainEntity ?? '', $validated, $primaryKey, $module);

        $this->qs = $validated['qs'];
        $this->mode = SearchMode::from($validated['mode'] ?? SearchMode::Auto->value);
    }
}
