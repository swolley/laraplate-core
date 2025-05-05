<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Override;
use Modules\Core\Models\DynamicEntity;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Casts\CrudRequestData;
use Modules\Core\Casts\IParsableRequest;
use Illuminate\Foundation\Http\FormRequest;

abstract class CrudRequest extends FormRequest implements IParsableRequest
{
    /** @var string|array<int,string> */
    protected string|array $primaryKey;

    protected Model $model;

    public function rules()
    {
        return [
            'connection' => ['string', 'sometimes'],
        ];
    }

    final public function getPrimaryKey(): array|string
    {
        return $this->primaryKey;
    }

    #[Override]
    public function parsed(): CrudRequestData
    {
        /** @phpstan-ignore method.notFound */
        return new CrudRequestData($this, $this->route('entity'), $this->validated(), $this->primaryKey ?? 'id');
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $connection = $this->connection ?? null;

        /** @phpstan-ignore method.notFound */
        $this->model = DynamicEntity::resolve($this->route('entity'), $connection);
        $this->primaryKey = $this->model->getKeyName();
    }
}
