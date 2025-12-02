<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use InvalidArgumentException;
use Modules\Core\Grids\Components\Grid;
use Modules\Core\Grids\Requests\GridRequest;
use Modules\Core\Helpers\PermissionChecker;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Models\DynamicEntity;
use PHPUnit\Framework\Exception as FrameworkException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\UnknownClassOrInterfaceException;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

final class GridsController extends Controller
{
    /**
     * @route-comment
     * Route(path: 'app/crud/grid/configs/{entity?}', name: 'core.crud.grids.getGridsConfigs', methods: [GET, HEAD], middleware: [web])
     */
    public function getGridsConfigs(Request $request, ?string $entity = null): \Illuminate\Http\JsonResponse
    {
        $response_builder = new ResponseBuilder($request);

        try {
            $grids = [];
            // $permissions = $request->user()->getAllPermissions();

            if (! in_array($entity, [null, '', '0'], true)) {
                $entity = $this->getModel($entity);
            }

            foreach (models() as $model) {
                /** @var Model $instance */
                $instance = new $model();
                $table = $instance->getTable();
                $grid = $this->getModelGridConfigs($entity, $instance, $table, $request/* , $permissions */);

                if ($grid !== null) {
                    $grids[$table] = $grid;
                }
            }

            if (! in_array($entity, [null, '', '0'], true)) {
                throw_if($grids === [], UnexpectedValueException::class, sprintf("'%s' is not a Grid", $entity));
                $grids = head($grids);
            }

            $response_builder->setData($grids);
        } catch (UnexpectedValueException $ex) {
            $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_BAD_REQUEST);
        } catch (UnauthorizedException $ex) {
            $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_UNAUTHORIZED);
        } finally {
            return $response_builder->json();
        }
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/grid/select/{entity}', name: 'core.crud.select', methods: [GET, POST, HEAD], middleware: [web])
     * Route(path: 'app/crud/grid/data/{entity}', name: 'core.crud.data', methods: [GET, POST, HEAD], middleware: [web])
     * Route(path: 'app/crud/grid/check/{entity}', name: 'core.crud.check', methods: [GET, HEAD], middleware: [web])
     * Route(path: 'app/crud/grid/layout/{entity}', name: 'core.crud.layout', methods: [GET, POST, PUT, PATCH, DELETE, HEAD], middleware: [web])
     * Route(path: 'app/crud/grid/export/{entity}', name: 'core.crud.export', methods: [GET, POST, HEAD], middleware: [web])
     * Route(path: 'app/crud/grid/insert/{entity}', name: 'core.crud.insert', methods: [POST], middleware: [web])
     * Route(path: 'app/crud/grid/update/{entity}', name: 'core.crud.replace', methods: [PATCH, PUT], middleware: [web])
     * Route(path: 'app/crud/grid/delete/{entity}', name: 'core.crud.delete', methods: [DELETE, POST], middleware: [web])
     */
    public function grid(GridRequest $request, string $entity): \Illuminate\Http\JsonResponse
    {
        try {
            $filters = $request->parsed();
            $model = DynamicEntity::resolve($entity, $filters['connection'] ?? null, request: $request);
            PermissionChecker::ensurePermissions($request, $model->getTable(), $filters->action->value, $model->getConnectionName());
            $grid = new Grid($model);

            return $grid->process($request);
        } catch (UnexpectedValueException|UnauthorizedException $ex) {
            return new ResponseBuilder($request)
                ->setData($ex)
                ->json();
        }
    }

    private function getModel(string $entity): string
    {
        $entity_instance = DynamicEntity::tryResolveModel($entity);

        throw_if(in_array($entity_instance, [null, '', '0'], true), UnexpectedValueException::class, sprintf("Unable to find entity '%s'", $entity));

        return $entity_instance;
    }

    /**
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     * @throws UnauthorizedException
     * @throws Exception
     * @throws FrameworkException
     * @throws ExpectationFailedException
     * @throws UnknownClassOrInterfaceException
     * @throws Exception
     * @throws UnexpectedValueException
     */
    private function getModelGridConfigs(string $entity, Model $instance, string $table, Request $request/* , Collection $permissions */): ?array
    {
        if (
            (in_array($entity, [null, '', '0'], true) || $instance::class === $entity::class)
            && Grid::useGridUtils($instance)
            && PermissionChecker::ensurePermissions($request, $table, connection: $instance->getConnectionName()/* , permissions: $permissions */)
        ) {
            /** @var Grid $grid */
            $grid = $instance->getGrid();

            return $grid->getConfigs();
        }

        return null;
    }
}
