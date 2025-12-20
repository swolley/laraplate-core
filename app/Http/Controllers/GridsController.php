<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use Modules\Core\Actions\Grids\GetGridConfigsAction;
use Modules\Core\Actions\Grids\ProcessGridAction;
use Modules\Core\Grids\Components\Grid;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Models\DynamicEntity;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

final class GridsController extends Controller
{
    public function __construct(
        private readonly GetGridConfigsAction $getGridConfigsAction,
        private readonly ProcessGridAction $processGridAction,
    ) {}

    /**
     * @route-comment
     * Route(path: 'app/crud/grid/configs/{entity?}', name: 'core.crud.grids.getGridsConfigs', methods: [GET, HEAD], middleware: [web])
     */
    public function getGridsConfigs(Request $request, ?string $entity = null): \Illuminate\Http\JsonResponse
    {
        $response_builder = new ResponseBuilder($request);

        try {
            $targetEntity = in_array($entity, [null, '', '0'], true) ? null : $this->getModel($entity);

            $response_builder->setData(($this->getGridConfigsAction)($request, $targetEntity));
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
    public function grid(\Modules\Core\Grids\Requests\GridRequest $request, string $entity): \Illuminate\Http\JsonResponse
    {
        try {
            return ($this->processGridAction)($request, $entity);
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
}
