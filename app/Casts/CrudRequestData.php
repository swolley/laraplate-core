<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Core\Models\DynamicEntity;

class CrudRequestData
{
    public protected(set) ?string $connection;

    /**
     * @phpstan-ignore property.uninitializedReadonly
     */
    public protected(set) Model $model;

    public function __construct(public readonly Request $request, public readonly string $mainEntity, array $validated, public readonly string|array $primaryKey, public readonly ?string $module = null)
    {
        $this->connection = $validated['connection'] ?? null;

        throw_if($this->mainEntity === '', InvalidArgumentException::class, 'Main entity is required');

        $this->model = DynamicEntity::resolve($this->mainEntity, $this->connection, request: $this->request, module: $this->module);
    }
}
