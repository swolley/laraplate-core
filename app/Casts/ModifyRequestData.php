<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Illuminate\Support\Str;
use Modules\Core\Http\Requests\ModifyRequest;

final class ModifyRequestData extends CrudRequestData
{
    public protected(set) array $changes = [];

    public function __construct(ModifyRequest $request, string $mainEntity, array $validated, string|array $primaryKey)
    {
        parent::__construct($request, $mainEntity, $validated, $primaryKey);

        foreach ($validated as $property => $value) {
            $this->changes[Str::replaceFirst($this->mainEntity . '.', '', $property)] = $value;
        }
    }

    /**
     * Allow read access to primary key and other fields from changes or request (e.g. id from route/query).
     */
    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->changes)) {
            return $this->changes[$name];
        }

        return $this->request->input($name) ?? $this->request->route($name);
    }
}
