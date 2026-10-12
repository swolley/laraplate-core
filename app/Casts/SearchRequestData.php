<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Modules\Core\Http\Requests\SearchRequest;
use Modules\Core\Search\Enums\SearchMode as SearchDepth;
use Modules\Core\Search\Enums\TextMatchPreference;

class SearchRequestData extends ListRequestData
{
    public readonly string $qs;

    public readonly SearchMode $mode;

    /**
     * How much the caller accepts to spend (`fast`, `balanced`, `deep`); an unknown value reads as `fast`.
     * Not to be confused with `mode`, which picks the path (`auto`, `basic`, `orchestrated`), nor with the `depth` of a graph search.
     */
    public SearchDepth $cost = SearchDepth::Fast;

    /**
     * How many times the caller lets a poor result be searched again; clamped later to the operator's cap.
     */
    public int $retry = 0;

    public TextMatchPreference $matching = TextMatchPreference::Auto;

    /**
     * @var array<string, mixed>
     */
    public array $matching_options = [];

    /**
     * @param  string|array<string>  $primaryKey
     */
    public function __construct(SearchRequest $request, ?string $mainEntity, array $validated, string|array $primaryKey, ?string $module = null)
    {
        parent::__construct($request, $mainEntity ?? '', $validated, $primaryKey, $module);

        $this->qs = $validated['qs'];
        $this->mode = SearchMode::from($validated['mode'] ?? SearchMode::Auto->value);
        $this->cost = SearchDepth::tryFrom((string) ($validated['cost'] ?? '')) ?? SearchDepth::Fast;
        $this->retry = max(0, (int) ($validated['retry'] ?? 0));
        $this->matching = TextMatchPreference::from($validated['matching'] ?? TextMatchPreference::Auto->value);
        $this->matching_options = is_array($validated['matching_options'] ?? null) ? $validated['matching_options'] : [];
    }
}
