<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\UnauthorizedException;
use LogicException;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Http\Requests\CrudRequest;
use Modules\Core\Http\Requests\DetailRequest;
use Modules\Core\Http\Requests\HistoryRequest;
use Modules\Core\Http\Requests\ListRequest;
use Modules\Core\Http\Requests\ModifyRequest;
use Modules\Core\Http\Requests\SearchRequest;
use Modules\Core\Http\Requests\TreeRequest;
use Modules\Core\Locking\Exceptions\AlreadyLockedException;
use Modules\Core\Locking\Exceptions\CannotUnlockException;
use Modules\Core\Locking\Exceptions\LockedModelException;
use Modules\Core\Services\Crud\CrudService;
use Modules\Core\Services\Crud\DTOs\CrudResult;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use UnexpectedValueException;

class CrudController extends Controller
{
    public function __construct(private readonly CrudService $crudService) {}

    /**
     * @route-comment
     * Route(path: 'api/v1/select/{entity}', name: 'core.api.list', methods: ['GET', 'POST', 'HEAD'], middleware: ['api', 'crud_api'])
     * Route(path: 'app/crud/select/{entity}', name: 'core.crud.list', methods: [GET, POST, HEAD], middleware: [web])
     */
    final public function list(ListRequest $request): Response
    {
        $requestData = $request->parsed();

        return $this->handleServiceCall(
            fn () => $this->crudService->list($requestData),
            $request,
            $requestData->model,
        );
    }

    /**
     * Show the specified resource.
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    /**
     * @route-comment
     * Route(path: 'api/v1/detail/{entity}', name: 'core.api.detail', methods: [GET, HEAD], middleware: [api, crud_api])
     * Route(path: 'app/crud/detail/{entity}', name: 'core.crud.detail', methods: [GET, HEAD], middleware: [web])
     */
    final public function detail(DetailRequest $request): Response
    {
        $requestData = $request->parsed();

        return $this->handleServiceCall(
            fn () => $this->crudService->detail($requestData),
            $request,
            $requestData->model,
        );
    }

    // /**
    //  * @route-comment
    //  * Route(path: 'api/v1/search/{entity?}', name: 'core.api.search', methods: [GET, POST, HEAD], middleware: [api, crud_api])
    //  * Route(path: 'app/crud/search/{entity?}', name: 'core.crud.search', methods: [GET, POST, HEAD], middleware: [web])
    //  */
    // public function search(SearchRequest $request): Response
    // {
    //     $requestData = $request->parsed();

    //     return $this->handleServiceCall(
    //         fn () => $this->crudService->search($requestData),
    //         $request,
    //         $requestData->model,
    //         shouldCache: false, // Search uses ElasticSearch, cache handled differently
    //     );
    // }

    /**
     * @route-comment
     * Route(path: 'api/v1/history/{entity}', name: 'core.api.history', methods: [GET, HEAD], middleware: [api, crud_api])
     * Route(path: 'app/crud/history/{entity}', name: 'core.crud.history', methods: [GET, HEAD], middleware: [web])
     */
    final public function history(HistoryRequest $request): Response
    {
        $requestData = $request->parsed();

        return $this->handleServiceCall(
            fn () => $this->crudService->history($requestData),
            $request,
            $requestData->model,
        );
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/tree/{entity}', name: 'core.api.tree', methods: [GET, HEAD], middleware: [api, crud_api])
     * Route(path: 'app/crud/tree/{entity}', name: 'core.crud.tree', methods: [GET, HEAD], middleware: [web])
     */
    final public function tree(TreeRequest $request): Response
    {
        $requestData = $request->parsed();

        return $this->handleServiceCall(
            fn () => $this->crudService->tree($requestData),
            $request,
            $requestData->model,
        );
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/insert/{entity}', name: 'core.api.insert', methods: [POST], middleware: [api, crud_api])
     * Route(path: 'app/crud/insert/{entity}', name: 'core.crud.insert', methods: [POST], middleware: [web])
     */
    final public function insert(ModifyRequest $request): Response
    {
        $requestData = $request->parsed();

        try {
            $result = $this->crudService->insert($requestData);

            // Invalidate cache after insert
            Cache::clearByEntity($requestData->model);

            return $this->buildResponse($result, $request);
        } catch (Throwable $ex) {
            return $this->handleServiceCall(fn () => throw $ex, $request, $requestData->model, shouldCache: false);
        }
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/update/{entity}', name: 'core.api.replace', methods: [PATCH, PUT], middleware: [api, crud_api])
     * Route(path: 'app/crud/update/{entity}', name: 'core.crud.replace', methods: [PATCH, PUT], middleware: [web])
     */
    final public function update(ModifyRequest $request): Response
    {
        $requestData = $request->parsed();

        try {
            $result = $this->crudService->update($requestData);

            // Invalidate cache after update
            Cache::clearByEntity($requestData->model);

            return $this->buildResponse($result, $request);
        } catch (Throwable $ex) {
            return $this->handleServiceCall(fn () => throw $ex, $request, $requestData->model, shouldCache: false);
        }
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/delete/{entity}', name: 'core.api.delete', methods: [DELETE, POST], middleware: [api, crud_api])
     * Route(path: 'app/crud/delete/{entity}', name: 'core.crud.delete', methods: [DELETE, POST], middleware: [web])
     */
    final public function delete(ModifyRequest $request): Response
    {
        $requestData = $request->parsed();

        try {
            $result = $this->crudService->delete($requestData);

            // Invalidate cache after delete
            Cache::clearByEntity($requestData->model);

            return $this->buildResponse($result, $request);
        } catch (Throwable $ex) {
            return $this->handleServiceCall(fn () => throw $ex, $request, $requestData->model, shouldCache: false);
        }
    }

    /**
     * @param  "activate"|"inactivate"  $operation
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     */
    final public function doActivateOperation(ModifyRequest $request, string $operation): Response
    {
        $requestData = $request->parsed();

        try {
            $result = $this->crudService->doActivateOperation($requestData, $operation);

            // Invalidate cache after activate/inactivate
            Cache::clearByEntity($requestData->model);

            return $this->buildResponse($result, $request);
        } catch (Throwable $ex) {
            return $this->handleServiceCall(fn () => throw $ex, $request, $requestData->model, shouldCache: false);
        }
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/activate/{entity}', name: 'core.crud.activate', methods: [PATCH], middleware: [web])
     */
    final public function activate(ModifyRequest $request): Response
    {
        return $this->doActivateOperation($request, 'activate');
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/inactivate/{entity}', name: 'core.crud.inactivate', methods: [PATCH], middleware: [web])
     */
    final public function inactivate(ModifyRequest $request): Response
    {
        return $this->doActivateOperation($request, 'inactivate');
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/approve/{entity}', name: 'core.crud.approve', methods: [PATCH], middleware: [web])
     */
    final public function approve(ModifyRequest $request): Response
    {
        $requestData = $request->parsed();

        try {
            $result = $this->crudService->approve($requestData);

            // Invalidate cache after approve
            Cache::clearByEntity($requestData->model);

            return $this->buildResponse($result, $request);
        } catch (Throwable $ex) {
            return $this->handleServiceCall(fn () => throw $ex, $request, $requestData->model, shouldCache: false);
        }
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/disapprove/{entity}', name: 'core.crud.disapprove', methods: [PATCH], middleware: [web])
     */
    final public function disapprove(ModifyRequest $request): Response
    {
        $requestData = $request->parsed();

        try {
            $result = $this->crudService->disapprove($requestData);

            // Invalidate cache after disapprove
            Cache::clearByEntity($requestData->model);

            return $this->buildResponse($result, $request);
        } catch (Throwable $ex) {
            return $this->handleServiceCall(fn () => throw $ex, $request, $requestData->model, shouldCache: false);
        }
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/lock/{entity}', name: 'core.crud.lock', methods: [PATCH], middleware: [web])
     */
    final public function lock(ModifyRequest $request): Response
    {
        $requestData = $request->parsed();

        try {
            $result = $this->crudService->lock($requestData);

            // Invalidate cache after lock
            Cache::clearByEntity($requestData->model);

            return $this->buildResponse($result, $request);
        } catch (Throwable $ex) {
            return $this->handleServiceCall(fn () => throw $ex, $request, $requestData->model, shouldCache: false);
        }
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/unlock/{entity}', name: 'core.crud.unlock', methods: [PATCH], middleware: [web])
     */
    final public function unlock(ModifyRequest $request): Response
    {
        $requestData = $request->parsed();

        try {
            $result = $this->crudService->unlock($requestData);

            // Invalidate cache after unlock
            Cache::clearByEntity($requestData->model);

            return $this->buildResponse($result, $request);
        } catch (Throwable $ex) {
            return $this->handleServiceCall(fn () => throw $ex, $request, $requestData->model, shouldCache: false);
        }
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/cache-clear/{entity}', name: 'core.crud.cache-clear', methods: [DELETE], middleware: [web])
     */
    final public function clearModelCache(CrudRequest $request): Response
    {
        $requestData = $request->parsed();
        $result = $this->crudService->clearModelCache($requestData);

        return $this->buildResponse($result, $request);
    }

    /**
     * Build HTTP Response from CrudResult.
     */
    private function buildResponse(CrudResult $result, Request $request): Response
    {
        $builder = new ResponseBuilder($request);
        $builder->setData($result->data);

        if ($result->meta) {
            if ($result->meta->totalRecords !== null) {
                $builder->setTotalRecords($result->meta->totalRecords);
            }

            if ($result->meta->currentRecords !== null) {
                $builder->setCurrentRecords($result->meta->currentRecords);
            }

            if ($result->meta->currentPage !== null) {
                $builder->setCurrentPage($result->meta->currentPage);
            }

            if ($result->meta->totalPages !== null) {
                $builder->setTotalPages($result->meta->totalPages);
            }

            if ($result->meta->pagination !== null) {
                $builder->setPagination($result->meta->pagination);
            }

            if ($result->meta->from !== null) {
                $builder->setFrom($result->meta->from);
            }

            if ($result->meta->to !== null) {
                $builder->setTo($result->meta->to);
            }

            if ($result->meta->class !== null) {
                $builder->setClass($result->meta->class);
            }

            if ($result->meta->table !== null) {
                $builder->setTable($result->meta->table);
            }

            if ($result->meta->cachedAt !== null) {
                $builder->setCachedAt($result->meta->cachedAt);
            }
        }

        if ($result->error) {
            $builder->setError($result->error);
        }

        if ($result->statusCode) {
            $builder->setStatus($result->statusCode);
        }

        return $builder->getResponse();
    }

    /**
     * Handle service call with error handling and optional caching.
     */
    private function handleServiceCall(callable $serviceCall, Request $request, ?Model $model = null, bool $shouldCache = true): Response
    {
        try {
            $result = $serviceCall();

            // Handle cache for read operations
            if ($model && $shouldCache && $this->shouldCache($request)) {
                return Cache::tryByRequest($model, $request, fn () => $this->buildResponse($result, $request));
            }

            return $this->buildResponse($result, $request);
        } catch (QueryException $ex) {
            return $this->buildResponse(
                new CrudResult(
                    data: null,
                    error: $ex->getMessage(),
                    statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                ),
                $request,
            );
        } catch (LockedModelException $ex) {
            return $this->buildResponse(
                new CrudResult(
                    data: null,
                    error: $ex->getMessage(),
                    statusCode: Response::HTTP_LOCKED,
                ),
                $request,
            );
        } catch (UnexpectedValueException|BadMethodCallException $ex) {
            return $this->buildResponse(
                new CrudResult(
                    data: null,
                    error: $ex->getMessage(),
                    statusCode: Response::HTTP_BAD_REQUEST,
                ),
                $request,
            );
        } catch (LogicException|AlreadyLockedException|CannotUnlockException $ex) {
            return $this->buildResponse(
                new CrudResult(
                    data: null,
                    error: $ex->getMessage(),
                    statusCode: Response::HTTP_NOT_MODIFIED,
                ),
                $request,
            );
        } catch (ModelNotFoundException $ex) {
            return $this->buildResponse(
                new CrudResult(
                    data: null,
                    error: $ex->getMessage(),
                    statusCode: Response::HTTP_NO_CONTENT,
                ),
                $request,
            );
        } catch (UnauthorizedException $ex) {
            return $this->buildResponse(
                new CrudResult(
                    data: null,
                    error: $ex->getMessage(),
                    statusCode: Response::HTTP_UNAUTHORIZED,
                ),
                $request,
            );
        } catch (Throwable $ex) {
            return $this->buildResponse(
                new CrudResult(
                    data: null,
                    error: $ex->getMessage(),
                    statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
                ),
                $request,
            );
        }
    }

    /**
     * Determine if request should be cached.
     */
    private function shouldCache(Request $request): bool
    {
        // Cache is enabled by default, can be disabled via query parameter
        return ! $request->boolean('no_cache');
    }
}
