<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Modules\Core\Http\Requests\SearchRequest;
use Modules\Core\Search\Enums\TextMatchPreference;

class SearchRequestData extends ListRequestData
{
    public readonly string $qs;

    public readonly SearchMode $mode;

    public TextMatchPreference $matching = TextMatchPreference::Auto;

    /** @var array<string, mixed> */
    public array $matching_options = [];

    /**
     * @param  string|array<string>  $primaryKey
     */
    public function __construct(SearchRequest $request, ?string $mainEntity, array $validated, string|array $primaryKey, ?string $module = null)
    {
        parent::__construct($request, $mainEntity ?? '', $validated, $primaryKey, $module);

        $this->qs = $validated['qs'];
        $this->mode = SearchMode::from($validated['mode'] ?? SearchMode::Auto->value);
        $this->matching = TextMatchPreference::from($validated['matching'] ?? TextMatchPreference::Auto->value);
        $this->matching_options = is_array($validated['matching_options'] ?? null) ? $validated['matching_options'] : [];
    }
}
