<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Illuminate\Http\Request;
use Modules\Core\Models\DynamicEntity;
use Illuminate\Database\Eloquent\Model;

final class CrudRequestData
{
    public readonly ?string $connection;

    /**
     * @phpstan-ignore property.uninitializedReadonly
     */
    public readonly Model $model;

    public function __construct(public readonly Request $request, public readonly string $mainEntity, array $validated, public readonly string|array $primaryKey)
    {
        $this->connection = $validated['connection'] ?? null;

        if ($this->mainEntity !== '') {
            $this->model = DynamicEntity::resolve($this->mainEntity, $this->connection, request: $this->request);
        }
    }
}
