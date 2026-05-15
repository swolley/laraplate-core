<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use Modules\Core\Actions\Grids\GetGridConfigsAction;
use Modules\Core\Actions\Grids\ProcessGridAction;
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
