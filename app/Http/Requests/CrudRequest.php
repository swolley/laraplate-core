<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\Casts\CrudRequestData;
use Modules\Core\Casts\IParsableRequest;
use Modules\Core\Models\DynamicEntity;
use Override;

/**
 * @inheritdoc
 * @package Modules\Core\Http\Requests
 * @property ?string $connection
 * @method mixed route(?string $name = null)
 * @method mixed input(string $name)
 * @method mixed validated(array|string $key = null, $default = null)
 * @method mixed get(string $key, $default = null)
 * @method mixed all(array|string $key = null, $default = null)
 * @method mixed input(string $key, $default = null)
 * @method mixed validated(array|string $key = null, $default = null)
 * @method mixed get(string $key, $default = null)
 * @method mixed all(array|string $key = null, $default = null)
 * @method self merge(array $to_merge)
 * @method string url()
 */
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
