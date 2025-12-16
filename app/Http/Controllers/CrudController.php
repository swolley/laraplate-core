<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Requests\DetailRequest;
use Modules\Core\Http\Requests\HistoryRequest;
use Modules\Core\Http\Requests\ListRequest;
use Modules\Core\Http\Requests\ModifyRequest;
use Modules\Core\Http\Requests\SearchRequest;
use Modules\Core\Http\Requests\TreeRequest;
use Modules\Core\Services\Crud\CrudService;
use Symfony\Component\HttpFoundation\Response;

class CrudController extends Controller
{
    public function __construct(private readonly CrudService $crudService) {}

    /**
     * @route-comment
     * Route(path: 'api/v1/select/{entity}', name: 'core.api.list', methods: ['GET', 'POST', 'HEAD'], middleware: ['api', 'crud_api'])
     * Route(path: 'app/crud/select/{entity}', name: 'core.crud.list', methods: [GET, POST, HEAD], middleware: [web])
     */
    public function list(ListRequest $request): Response
    {
        return $this->crudService->list($request);
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
    public function detail(DetailRequest $request): Response
    {
        return $this->crudService->detail($request);
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/search/{entity?}', name: 'core.api.search', methods: [GET, POST, HEAD], middleware: [api, crud_api])
     * Route(path: 'app/crud/search/{entity?}', name: 'core.crud.search', methods: [GET, POST, HEAD], middleware: [web])
     */
    public function search(SearchRequest $request): Response
    {
        return $this->crudService->search($request);
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/history/{entity}', name: 'core.api.history', methods: [GET, HEAD], middleware: [api, crud_api])
     * Route(path: 'app/crud/history/{entity}', name: 'core.crud.history', methods: [GET, HEAD], middleware: [web])
     */
    public function history(HistoryRequest $request): Response
    {
        return $this->crudService->history($request);
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/tree/{entity}', name: 'core.api.tree', methods: [GET, HEAD], middleware: [api, crud_api])
     * Route(path: 'app/crud/tree/{entity}', name: 'core.crud.tree', methods: [GET, HEAD], middleware: [web])
     */
    public function tree(TreeRequest $request): Response
    {
        return $this->crudService->tree($request);
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/insert/{entity}', name: 'core.api.insert', methods: [POST], middleware: [api, crud_api])
     * Route(path: 'app/crud/insert/{entity}', name: 'core.crud.insert', methods: [POST], middleware: [web])
     */
    public function insert(Request $request): Response
    {
        return $this->crudService->insert($request);
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/update/{entity}', name: 'core.api.replace', methods: [PATCH, PUT], middleware: [api, crud_api])
     * Route(path: 'app/crud/update/{entity}', name: 'core.crud.replace', methods: [PATCH, PUT], middleware: [web])
     */
    public function update(ModifyRequest $request): Response
    {
        return $this->crudService->update($request);
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/delete/{entity}', name: 'core.api.delete', methods: [DELETE, POST], middleware: [api, crud_api])
     * Route(path: 'app/crud/delete/{entity}', name: 'core.crud.delete', methods: [DELETE, POST], middleware: [web])
     */
    public function delete(ModifyRequest $request): Response
    {
        return $this->crudService->delete($request);
    }

    /**
     * @param  "activate"|"inactivate"  $operation
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     */
    public function doActivateOperation(ModifyRequest $request, string $operation): Response
    {
        return $this->crudService->doActivateOperation($request, $operation);
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/activate/{entity}', name: 'core.crud.activate', methods: [PATCH], middleware: [web])
     */
    public function activate(ModifyRequest $request): Response
    {
        return $this->doActivateOperation($request, 'activate');
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/inactivate/{entity}', name: 'core.crud.inactivate', methods: [PATCH], middleware: [web])
     */
    public function inactivate(ModifyRequest $request): Response
    {
        return $this->doActivateOperation($request, 'inactivate');
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/approve/{entity}', name: 'core.crud.approve', methods: [PATCH], middleware: [web])
     */
    public function approve(ModifyRequest $request): Response
    {
        return $this->crudService->approve($request);
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/disapprove/{entity}', name: 'core.crud.disapprove', methods: [PATCH], middleware: [web])
     */
    public function disapprove(ModifyRequest $request): Response
    {
        return $this->crudService->disapprove($request);
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/lock/{entity}', name: 'core.crud.lock', methods: [PATCH], middleware: [web])
     */
    public function lock(ModifyRequest $request): Response
    {
        return $this->crudService->lock($request);
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/unlock/{entity}', name: 'core.crud.unlock', methods: [PATCH], middleware: [web])
     */
    public function unlock(ModifyRequest $request): Response
    {
        return $this->crudService->unlock($request);
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/cache-clear/{entity}', name: 'core.crud.cache-clear', methods: [DELETE], middleware: [web])
     */
    public function clearModelCache(Request $request): Response
    {
        return $this->crudService->clearModelCache($request);
    }
}
