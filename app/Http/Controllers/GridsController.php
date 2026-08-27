<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Modules\Core\Actions\Grids\GetGridConfigsAction;
use Modules\Core\Actions\Grids\ProcessGridAction;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Models\DynamicEntity;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

/**
 * @deprecated The Grid subsystem is being retired. Its Funnels concept survives as
 *             Facets: see Modules\Core\Services\Crud\DTOs\FacetQuery and the facet
 *             handling in Modules\Core\Services\Crud\CrudService. Do not build on
 *             this class.
 */
final class GridsController extends Controller
{
    public function __construct(
        private readonly GetGridConfigsAction $getGridConfigsAction,
        private readonly ProcessGridAction $processGridAction,
    ) {}

    public function getGridsConfigs(Request $request, string $module, ?string $entity = null): \Illuminate\Http\JsonResponse
    {
        $response_builder = new ResponseBuilder($request);

        try {
            $targetEntity = in_array($entity, [null, '', '0'], true) ? null : $this->getModel($module, $entity);

            $response_builder->setData(($this->getGridConfigsAction)($request, $targetEntity, $module));
        } catch (UnexpectedValueException $ex) {
            $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_BAD_REQUEST);
        } catch (AuthorizationException $ex) {
            $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_UNAUTHORIZED);
        } finally {
            return $response_builder->json();
        }
    }

    public function grid(\Modules\Core\Grids\Requests\GridRequest $request, string $module, string $entity): \Illuminate\Http\JsonResponse
    {
        try {
            return ($this->processGridAction)($request, $entity, $module);
        } catch (UnexpectedValueException|AuthorizationException $ex) {
            return new ResponseBuilder($request)
                ->setData($ex)
                ->json();
        }
    }

    private function getModel(string $module, string $entity): string
    {
        $entity_instance = DynamicEntity::tryResolveModel($entity, module: $module);

        throw_if(in_array($entity_instance, [null, '', '0'], true), UnexpectedValueException::class, sprintf("Unable to find entity '%s' in module '%s'", $entity, $module));

        return $entity_instance;
    }
}
