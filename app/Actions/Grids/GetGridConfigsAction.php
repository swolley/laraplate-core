<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Grids;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Core\Grids\Components\Grid;
use Modules\Core\Inspector\ModelMetadataRegistry;
use Modules\Core\Services\Authorization\AuthorizationService;
use PHPUnit\Framework\Exception as FrameworkException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\UnknownClassOrInterfaceException;
use ReflectionClass;
use UnexpectedValueException;

final readonly class GetGridConfigsAction
{
    public function __construct(
        private AuthorizationService $auth,
        private ?Closure $modelsProvider = null,
        private ?Closure $gridResolver = null,
    ) {}

    /**
     * @throws BindingResolutionException
     * @throws UnexpectedValueException
     * @throws FrameworkException
     * @throws ExpectationFailedException
     * @throws UnknownClassOrInterfaceException
     */
    public function __invoke(Request $request, ?string $entity = null, ?string $module = null): array
    {
        $models = $this->modelsProvider instanceof Closure ? ($this->modelsProvider)() : models();
        $registry = ModelMetadataRegistry::getInstance();
        $grids = [];

        foreach ($models as $model) {
            if (
                $module !== null
                && $module !== ''
                && strcasecmp((string) class_module($model), $module) !== 0
            ) {
                continue;
            }

            if ($this->gridResolver instanceof Closure) {
                $grid = ($this->gridResolver)($model, $entity, $request, $module);

                if ($grid !== null) {
                    $grids[$model] = $grid;
                }

                continue;
            }

            $meta = $registry->get($model);

            if (! $meta->hasGridUtils) {
                continue;
            }

            /** @var Model $instance */
            $instance = new ReflectionClass($model)->newInstanceWithoutConstructor();
            $grid = $this->getModelGridConfigs($entity ?? '', $instance, $meta->table, $request);

            if ($grid !== null) {
                $grids[$meta->table] = $grid;
            }
        }

        if (! in_array($entity, [null, '', '0'], true)) {
            throw_if($grids === [], UnexpectedValueException::class, sprintf("'%s' is not a Grid", $entity));
            $grids = head($grids);
        }

        return $grids;
    }

    /**
     * @throws UnexpectedValueException
     * @throws BindingResolutionException
     * @throws UnknownClassOrInterfaceException
     * @throws ExpectationFailedException
     * @throws FrameworkException
     * @throws Exception
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    private function getModelGridConfigs(string $entity, Model $instance, string $table, Request $request): ?array
    {
        if (
            (! in_array($entity, [null, '', '0'], true) && $instance::class !== $entity::class)
            || ! Grid::useGridUtils($instance)
        ) {
            return null;
        }

        // Grid visibility is derived from the operations the user may actually
        // perform on the entity. An operation-less entity gate cannot work here:
        // its permission name ("{connection}.{table}.") has an empty operation
        // segment and is rejected by the permission name convention, so it would
        // only ever pass for superadmins.
        $operations = $this->auth->allowedOperations(
            $request,
            $table,
            $instance->getConnectionName(),
        );

        if ($operations === []) {
            return null;
        }

        /** @var Grid $grid */
        $grid = $instance->getGrid();

        $configs = $grid->getConfigs();
        $configs['operations'] = $operations;

        return $configs;
    }
}
