<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use UnexpectedValueException;
use Modules\Core\Models\DynamicEntity;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Components\Grid;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Helpers\PermissionChecker;
use Modules\Core\Grids\Requests\GridRequest;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\UnauthorizedException;

final class GridsController extends Controller
{
    /**
     * @route-comment
     * Route(path: 'app/crud/grid/configs/{entity?}', name: 'core.crud.grids.getGridsConfigs', methods: [GET, HEAD], middleware: [web])
     */
    public function getGridsConfigs(Request $request, ?string $entity = null)
    {
        $response_builder = new ResponseBuilder($request);

        try {
            $grids = [];
            $permissions = $request->user()->getAllPermissions();

            if ($entity !== null && $entity !== '' && $entity !== '0') {
                $entity_instance = DynamicEntity::tryResolveModel($entity);

                if ($entity_instance === null || $entity_instance === '' || $entity_instance === '0') {
                    throw new UnexpectedValueException("Unable to find entity '{$entity}'");
                }
                $entity = $entity_instance;
            }

            foreach (models() as $model) {
                /** @var Model $instance */
                $instance = new $model();
                $table = $instance->getTable();

                if (
                    ($entity === null || $entity === '' || $entity === '0' || $instance::class === $entity::class)
                    && Grid::useGridUtils($instance)
                    && PermissionChecker::ensurePermissions($request, $table, connection: $instance->getConnectionName(), permissions: $permissions)
                ) {
                    /** @var Grid $grid */
                    $grid = $instance->getGrid();
                    $grids[$table] = $grid->getConfigs();
                }
            }

            if ($entity !== null && $entity !== '' && $entity !== '0') {
                if ($grids === []) {
                    throw new UnexpectedValueException("'{$entity}' is not a Grid");
                }
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
    public function grid(GridRequest $request, string $entity): Response
    {
        try {
            // $filters = $request->parsed();
            $model = DynamicEntity::resolve($entity, $filters['connection'] ?? null, request: $request);
            PermissionChecker::ensurePermissions($request, $model->getTable(), $request->action, $model->getConnectionName());
            $grid = new Grid($model);

            return $grid->process($request);
        } catch (UnexpectedValueException|UnauthorizedException $ex) {
            return new ResponseBuilder($request)
                ->setData($ex)
                ->json();
        }
    }
}
