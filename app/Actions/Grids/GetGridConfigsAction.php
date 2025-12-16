<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Grids;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Core\Grids\Components\Grid;
use Modules\Core\Helpers\PermissionChecker;
use PHPUnit\Framework\Exception as FrameworkException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\UnknownClassOrInterfaceException;
use ReflectionClass;
use UnexpectedValueException;

final readonly class GetGridConfigsAction
{
    public function __construct(
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
    public function __invoke(Request $request, ?string $entity = null): array
    {
        $models = $this->modelsProvider instanceof Closure ? ($this->modelsProvider)() : models();
        $grids = [];

        foreach ($models as $model) {
            if ($this->gridResolver instanceof Closure) {
                $grid = ($this->gridResolver)($model, $entity, $request);

                if ($grid !== null) {
                    $grids[$model] = $grid;
                }

                continue;
            }

            /** @var Model $instance */
            $instance = new ReflectionClass($model)->newInstanceWithoutConstructor();
            $table = $instance->getTable();
            $grid = $this->getModelGridConfigs($entity, $instance, $table, $request);

            if ($grid !== null) {
                $grids[$table] = $grid;
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
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    private function getModelGridConfigs(string $entity, Model $instance, string $table, Request $request): ?array
    {
        if (
            (in_array($entity, [null, '', '0'], true) || $instance::class === $entity::class)
            && Grid::useGridUtils($instance)
            && PermissionChecker::ensurePermissions($request, $table, connection: $instance->getConnectionName())
        ) {
            /** @var Grid $grid */
            $grid = $instance->getGrid();

            return $grid->getConfigs();
        }

        return null;
    }
}
