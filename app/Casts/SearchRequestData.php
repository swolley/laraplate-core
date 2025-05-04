<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Modules\Core\Http\Requests\SearchRequest;

final class SearchRequestData extends ListRequestData
{
    public readonly string $qs;

    /**
     * @param  string|array<string>  $primaryKey
     */
    public function __construct(SearchRequest $request, ?string $mainEntity, array $validated, string|array $primaryKey)
    {
        parent::__construct($request, $mainEntity ?? '', $validated, $primaryKey);

        $this->qs = $validated['qs'];
    }
}
