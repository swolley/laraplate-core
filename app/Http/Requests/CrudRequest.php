<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use function modules;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Modules\Core\Casts\CrudRequestData;
use Modules\Core\Casts\IParsableRequest;
use Modules\Core\Models\DynamicEntity;
use Nwidart\Modules\Facades\Module;
use Override;

/**
 * @property ?string $connection
 *
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
    /**
     * @var string|array<int,string>
     */
    protected string|array $primaryKey = 'id';

    protected ?Model $model = null;

    public function rules(): array
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
        return new CrudRequestData($this, $this->resolveMainEntity(), $this->validated(), $this->primaryKey ?? 'id', $this->input('module'));
    }

    protected function resolveMainEntity(): string
    {
        /** @var string|null $entity */
        $entity = $this->input('entity') ?? $this->route('entity');

        return (string) ($entity ?? '');
    }

    protected function resolveModule(): ?string
    {
        /** @var string|null $module */
        $module = $this->route('module') ?? $this->input('module');

        if ($module === null || $module === '') {
            return null;
        }

        $studly_module = Str::studly($module);
        $registered_module = $this->resolveRegisteredModuleName($module, $studly_module);

        if ($registered_module !== null) {
            return $registered_module;
        }

        if (Module::has($module)) {
            return $module;
        }

        if (Module::has($studly_module)) {
            return $studly_module;
        }

        return $module;
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        $connection = $this->connection ?? null;
        $entity = $this->resolveMainEntity();
        $module = $this->resolveModule();

        /** @phpstan-ignore method.notFound */
        $this->model = DynamicEntity::resolve($entity, $connection, [], null, $module);
        $this->primaryKey = $this->model->getKeyName();

        /** @phpstan-ignore method.notFound */
        $this->merge([
            'entity' => $this->route('entity') ?? $entity,
            'module' => $module,
        ]);
    }

    private function resolveRegisteredModuleName(string $module, string $studly_module): ?string
    {
        if (! function_exists('modules')) {
            return null;
        }

        /** @var list<string> $registered_modules */
        $registered_modules = modules(false, false, false);

        foreach ($registered_modules as $registered_module) {
            if (strcasecmp($registered_module, $module) === 0 || strcasecmp($registered_module, $studly_module) === 0) {
                return $registered_module;
            }
        }

        return null;
    }
}
