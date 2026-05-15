<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Modules\Core\Http\Requests\TreeRequest;

final class TreeRequestData extends DetailRequestData
{
    public protected(set) bool $parents;

    public protected(set) bool $children;

    /**
     * @param  array{parents:bool,children:bool}  $validated
     * @param  string|array<string>  $primaryKey
     */
    public function __construct(TreeRequest $request, string $mainEntity, array $validated, string|array $primaryKey, ?string $module = null)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey, $module);
        $this->parents = $validated['parents'] ?? false;
        $this->children = $validated['children'] ?? false;
    }
}
