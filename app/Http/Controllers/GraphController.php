<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Core\Graph\GraphService;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Http\Requests\ExpandGraphRequest;
use Modules\Core\Services\Crud\DTOs\CrudMeta;
use Modules\Core\Services\Crud\DTOs\CrudResult;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class GraphController extends Controller
{
    public function __construct(private readonly GraphService $graphs) {}

    public function expand(ExpandGraphRequest $request): Response
    {
        try {
            return $this->buildResponse($this->graphs->expand($request->parsed()), $request);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (ModelNotFoundException $exception) {
            return $this->buildResponse(new CrudResult(null, error: $exception->getMessage(), statusCode: Response::HTTP_NOT_FOUND), $request);
        } catch (AuthorizationException $exception) {
            return $this->buildResponse(new CrudResult(null, error: $exception->getMessage(), statusCode: Response::HTTP_UNAUTHORIZED), $request);
        } catch (Throwable $exception) {
            report($exception);

            return $this->buildResponse(new CrudResult(null, error: $exception->getMessage(), statusCode: Response::HTTP_INTERNAL_SERVER_ERROR), $request);
        }
    }

    private function buildResponse(CrudResult $result, Request $request): Response
    {
        $builder = new ResponseBuilder($request);
        $builder->setData($result->data);

        if ($result->meta instanceof CrudMeta) {
            if ($result->meta->class !== null) {
                $builder->setClass($result->meta->class);
            }

            if ($result->meta->table !== null) {
                $builder->setTable($result->meta->table);
            }

            if ($result->meta->cachedAt !== null) {
                $builder->setCachedAt();
            }
        }

        if ($result->error !== null) {
            $builder->setError($result->error);
        }

        if ($result->statusCode !== null) {
            $builder->setStatus($result->statusCode);
        }

        return $builder->getResponse();
    }
}
