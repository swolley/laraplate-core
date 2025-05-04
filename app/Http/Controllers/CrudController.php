<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Closure;
use Throwable;
use BadMethodCallException;
use Doctrine\DBAL\Exception;
use Illuminate\Http\Request;
use UnexpectedValueException;
use Illuminate\Support\Carbon;
use Modules\Core\Casts\Filter;
use Modules\Core\Crud\CrudHelper;
use Modules\Core\Casts\WhereClause;
use Illuminate\Foundation\Auth\User;
use Modules\Core\Casts\FiltersGroup;
use Approval\Traits\RequiresApproval;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\Modification;
use Modules\Core\Casts\FilterOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Modules\Core\Casts\CrudRequestData;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\TreeRequestData;
use Elastic\Elasticsearch\ClientBuilder;
use Modules\Core\Casts\IParsableRequest;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Casts\DetailRequestData;
use Modules\Core\Casts\ModifyRequestData;
use Modules\Core\Casts\SearchRequestData;
use Modules\Core\Casts\SelectRequestData;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Casts\HistoryRequestData;
use Modules\Core\Search\Traits\Searchable;
use Modules\Core\Helpers\HasCrudOperations;
use Modules\Core\Helpers\PermissionChecker;
use Modules\Core\Http\Requests\ListRequest;
use Modules\Core\Http\Requests\TreeRequest;
use Illuminate\Database\Eloquent\Collection;
use Overtrue\LaravelVersionable\Versionable;
use Modules\Core\Http\Requests\DetailRequest;
use Modules\Core\Http\Requests\ModifyRequest;
use Modules\Core\Http\Requests\SearchRequest;
use Modules\Core\Http\Requests\HistoryRequest;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Core\Locking\Exceptions\LockedModelException;
use Modules\Core\Locking\Exceptions\CannotUnlockException;
use Modules\Core\Locking\Exceptions\AlreadyLockedException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3SmallEmbeddingGenerator;

class CrudController extends Controller
{
    use HasCrudOperations;

    /**
     * checks if model uses recursive relationships trait
     */
    private function useRecursiveRelationships(Model $model): bool
    {
        return class_uses_trait($model, HasRecursiveRelationships::class);
    }

    /**
     * checks if model uses approval trait
     */
    private function useHasApproval(Model $model): bool
    {
        return class_uses_trait($model, RequiresApproval::class);
    }

    /**
     * checks if model uses versionable trait
     */
    private function hasHistory(Model $model): bool
    {
        return class_uses_trait($model, Versionable::class);
    }

    private function isParsableRequest(Request $request): bool
    {
        return in_array(\Modules\Core\Casts\IParsableRequest::class, class_implements($request));
    }

    /**
     * @return string|array<int,string>
     */
    private function getModelKeyValue(CrudRequestData $filters): string|array
    {
        /** @var string|array<int,string> $key */
        $key = $filters->model->getKeyName();
        if (is_string($key)) {
            return $filters->$key;
        }
        $key_value = array_flip($key);
        foreach ($key as $k) {
            $key_value[$k] = $filters->$k;
        }

        return $key_value;
    }

    /**
     * @param  Closure(ResponseBuilder, SelectRequestData): ResponseBuilder  $operation
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     */
    private function executeOperation(Request|IParsableRequest $request, Closure $operation): Response
    {
        $response_builder = new ResponseBuilder($request);
        $filters = $this->isParsableRequest($request) ? $request->parsed() : $request->validated();

        try {
            return $operation($response_builder, $filters)->getResponse();
        } catch (QueryException $ex) {
            return $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
                ->getResponse();
        } catch (LockedModelException $ex) {
            return $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_LOCKED)
                ->getResponse();
        } catch (UnexpectedValueException | BadMethodCallException $ex) {
            return $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_BAD_REQUEST)
                ->getResponse();
        } catch (\LogicException | AlreadyLockedException | CannotUnlockException $ex) {
            return $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_NOT_MODIFIED)
                ->getResponse();
        } catch (ModelNotFoundException $ex) {
            return $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_NO_CONTENT)
                ->getResponse();
        } catch (UnauthorizedException $ex) {
            return $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_UNAUTHORIZED)
                ->getResponse();
        } catch (Throwable $ex) {
            return $response_builder
                ->setData($ex)
                ->setStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
                ->getResponse();
        }
    }

    //region READ OPERATIONS

    /**
     * @route-comment
     * Route(path: 'api/v1/select/{entity}', name: 'core.api.list', methods: [GET, POST, HEAD], middleware: [api])
     * Route(path: 'app/crud/select/{entity}', name: 'core.crud.list', methods: [GET, POST, HEAD], middleware: [web])
     */
    public function list(ListRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, ListRequestData $filters): Response {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return Cache::tryByRequest($model, $filters->request, function () use ($model, $filters, $responseBuilder) {
                $query = $model::query();
                $crud_helper = new CrudHelper();
                $crud_helper->prepareQuery($query, $filters);

                $total_records = $query->count();

                $data = match (true) {
                    isset($filters->page) => $this->listByPagination($query, $filters, $responseBuilder, $total_records),
                    isset($filters->from) => $this->listByFromTo($query, $filters, $responseBuilder, $total_records),
                    default => $this->listByOthers($query, $filters, $responseBuilder, $total_records)
                };

                if (isset($filters->group_by)) {
                    $data = $this->applyGroupBy($data, $filters->group_by);
                }

                return $responseBuilder
                    ->setClass($model)
                    ->setData($data)
                    ->setCachedAt(Carbon::now());
            });
        });
    }

    /**
     * Show the specified resource
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    /**
     * @route-comment
     * Route(path: 'api/v1/detail/{entity}', name: 'core.api.detail', methods: [GET, HEAD], middleware: [api])
     * Route(path: 'app/crud/detail/{entity}', name: 'core.crud.detail', methods: [GET, HEAD], middleware: [web])
     */
    public function detail(DetailRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, DetailRequestData $filters): Response {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return Cache::tryByRequest($model, $filters->request, function () use ($model, $filters, $responseBuilder) {
                $query = $model::query();
                $crud_helper = new CrudHelper();
                $crud_helper->prepareQuery($query, $filters);

                return $responseBuilder
                    ->setClass($model)
                    ->setData($query->sole())
                    ->setCachedAt(Carbon::now());
            });
        });
    }

    private function getElasticSearchQuery(SearchRequestData $filters, ?array $embeddings = null): array
    {
        $templateKey = 'elastic_template:' . md5(serialize([$filters->filters, $embeddings]));

        $cache = Cache::store();
        $cachedTemplate = $cache->get($templateKey);
        if ($cachedTemplate) {
            return $cachedTemplate;
        }

        $params = [
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [],
                        'should' => [],
                        'minimum_should_match' => 1
                    ]
                ]
            ]
        ];

        if ($embeddings) {
            $params['body']['query']['bool']['should'][] = [
                'script_score' => [
                    'query' => ['match_all' => new \stdClass()],
                    'script' => [
                        'source' => "cosineSimilarity(params.query_vector, 'embedding') + 1.0",
                        'params' => ['query_vector' => $embeddings]
                    ]
                ]
            ];
        }

        if ($filters->filters instanceof FiltersGroup) {
            $params['body']['query']['bool']['must'] = $this->translateFiltersToElasticsearch($filters->filters);
        }

        // Aggiungiamo ottimizzazioni per la query
        $params['body']['_source'] = ['includes' => $filters->fields ?? ['*']];
        $params['body']['sort'] = ['_score' => ['order' => 'desc']];

        if ($filters->mainEntity !== '' && $filters->mainEntity !== '0') {
            $params['index'] = $filters->mainEntity;
        }

        if ($filters->take) {
            $params['size'] = $filters->take;
        }

        $cache->put($templateKey, $params, 3600); // cache per 1 ora

        return $params;
    }

    private function translateFiltersToElasticsearch(FiltersGroup $filtersGroup): array
    {
        $mustClauses = [];

        foreach ($filtersGroup->filters as $filter) {
            if ($filter instanceof Filter) {
                $mustClauses[] = $this->translateFilterToElasticsearch($filter);
            } elseif ($filter instanceof FiltersGroup) {
                $clause = $filter->operator === WhereClause::AND ? 'must' : 'should';
                $mustClauses[] = [
                    'bool' => [
                        $clause => $this->translateFiltersToElasticsearch($filter),
                    ],
                ];
            }
        }

        return $mustClauses;
    }

    private function translateFilterToElasticsearch(Filter $filter): array
    {
        return match ($filter->operator) {
            FilterOperator::EQUALS => ['term' => [$filter->property => $filter->value]],
            FilterOperator::NOT_EQUALS => ['bool' => ['must_not' => ['term' => [$filter->property => $filter->value]]]],
            FilterOperator::LIKE => ['wildcard' => [$filter->property => '*' . $filter->value . '*']],
            FilterOperator::NOT_LIKE => ['bool' => ['must_not' => ['wildcard' => [$filter->property => '*' . $filter->value . '*']]]],
            FilterOperator::IN => ['terms' => [$filter->property => $filter->value]],
            FilterOperator::GREAT => ['gt' => [$filter->property => $filter->value]],
            FilterOperator::GREAT_EQUALS => ['gte' => [$filter->property => $filter->value]],
            FilterOperator::LESS => ['lt' => [$filter->property => $filter->value]],
            FilterOperator::LESS_EQUALS => ['lte' => [$filter->property => $filter->value]],
            FilterOperator::BETWEEN => ['range' => [$filter->property => ['gte' => $filter->value[0], 'lte' => $filter->value[1]]]],
        };
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/search/{entity?}', name: 'core.api.search', methods: [GET, POST, HEAD], middleware: [api])
     * Route(path: 'app/crud/search/{entity?}', name: 'core.crud.search', methods: [GET, POST, HEAD], middleware: [web])
     */
    public function search(SearchRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, SearchRequestData $filters): Response {
            $is_searchable_class = class_uses_trait($filters->model, Searchable::class);
            if (!isset($filters->model) || $is_searchable_class) {
                $embeddedDocument = null;

                if (property_exists($filters->model, 'embed') && $filters->model->embed !== null && $filters->model->embed !== []) {
                    $embeddingGenerator = new OpenAI3SmallEmbeddingGenerator();
                    $embeddedDocument = $embeddingGenerator->embedText($filters->qs);
                }

                // Pass both embeddedDocument and filters->filters to the query
                $elastic_query = $this->getElasticSearchQuery($filters, $embeddedDocument);

                $client = ClientBuilder::create()->build();
                $response = $client->search($elastic_query);
                $totalRecords = $response['hits']['total']['value'] ?? 0;
                $data = $response['hits']['hits'] ?? [];

                $responseBuilder
                    ->setTotalRecords($totalRecords)
                    ->setCurrentRecords(count($data))
                    ->setPagination($filters->pagination)
                    ->setCurrentPage($filters->page)
                    ->setTotalPages($filters->calculateTotalPages($totalRecords));

                return $responseBuilder
                    ->setData($data)
                    ->getResponse();
            }

            return $responseBuilder->setError('Full-search operation can be done only on Searchable entities')->getResponse();
        });
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/history/{entity}', name: 'core.api.history', methods: [GET, HEAD], middleware: [api])
     * Route(path: 'app/crud/history/{entity}', name: 'core.crud.history', methods: [GET, HEAD], middleware: [web])
     */
    public function history(HistoryRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, HistoryRequestData $filters): Response {
            $model = $filters->model;
            if (!$this->hasHistory($model)) {
                throw new BadMethodCallException("'$filters->mainEntity' doesn't have history handling");
            }
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return Cache::tryByRequest($model, $filters->request, function () use ($model, $filters, $responseBuilder) {
                $query = $model::query();
                $crud_helper = new CrudHelper();
                $crud_helper->prepareQuery($query, $filters);
                $query->with('history', function (Builder $q) use ($filters) {
                    $q->latest();
                    if (isset($filters->limit)) {
                        $q->take($filters->limit);
                    }
                });
                if (!preview() && $this->useHasApproval($model)) {
                    $query->with('modifications');
                }

                return $responseBuilder
                    ->setClass($model)
                    ->setData($query->sole())
                    ->setCachedAt(Carbon::now());
            });
        });
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/tree/{entity}', name: 'core.api.tree', methods: [GET, HEAD], middleware: [api])
     * Route(path: 'app/crud/tree/{entity}', name: 'core.crud.tree', methods: [GET, HEAD], middleware: [web])
     */
    public function tree(TreeRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, TreeRequestData $filters): Response {
            $model = $filters->model;
            if (!$this->useRecursiveRelationships($model)) {
                throw new UnexpectedValueException("'$filters->mainEntity' is not a hierarchical class");
            }
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return Cache::tryByRequest($model, $filters->request, function () use ($model, $filters, $responseBuilder) {
                $tree_relation_type = [];
                if ($filters->parents && $filters->children) {
                    $tree_relation_type = 'bloodline';
                } elseif ($filters->parents) {
                    $tree_relation_type = 'ancestorsAndSelf';
                } elseif ($filters->children) {
                    $tree_relation_type = 'descendantsAndSelf';
                }

                $query = $model::with($tree_relation_type);
                $crud_helper = new CrudHelper();
                $crud_helper->prepareQuery($query, $filters);

                return $responseBuilder
                    ->setClass($model)
                    ->setData($query->sole())
                    ->setCachedAt(Carbon::now());
            });
        });
    }

    //endregion

    //region WRITE OPERATIONS

    private function removeNotFillableProperties(Model $model, array &$values): array
    {
        $non_fillables = array_diff(array_keys($model->getFillable()), array_keys($values));
        foreach ($non_fillables as $property) {
            unset($values[$property]);
        }

        return $non_fillables;
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/insert/{entity}', name: 'core.api.insert', methods: [POST], middleware: [api])
     * Route(path: 'app/crud/insert/{entity}', name: 'core.crud.insert', methods: [POST], middleware: [web])
     */
    public function insert(Request $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, ModifyRequestData $values, $request): Response {
            $model = $values->model;
            PermissionChecker::ensurePermissions($values->request, $model->getTable(), 'insert', $model->getConnectionName());
            // $values = method_exists($model, 'getRules') ? $request->validate($model->getRules()) : $filters;
            // $values = $request->all();
            // se ci sono proprietà che non sono nei fillable devo restituire errore?
            $discarded_values = $this->removeNotFillableProperties($model, $values->changes);

            $created = $model->create($values->changes);
            if (!$created) {
                throw new \LogicException('Record not created');
            }

            $created->fresh();

            return $responseBuilder
                ->setData($created)
                ->setStatus(Response::HTTP_CREATED)
                ->setError($discarded_values === [] ? null : $discarded_values)
                ->getResponse();
        });
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/update/{entity}', name: 'core.api.replace', methods: [PATCH, PUT], middleware: [api])
     * Route(path: 'app/crud/update/{entity}', name: 'core.crud.replace', methods: [PATCH, PUT], middleware: [web])
     */
    public function update(ModifyRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, ModifyRequestData $values): Response {
            $model = $values->model;
            PermissionChecker::ensurePermissions($values->request, $model->getTable(), 'update', $model->getConnectionName());
            // if $filters->request->method() == 'PUT' devo sovrascrivere tutto il record quindi devono esserci tutti i fillable e devo fare le validazioni
            // else valido quello che ho con le regole che ho se le ho
            if ($model->usesTimestamps() && !isset($values->{$model::UPDATED_AT})) {
                throw new BadMethodCallException($model::UPDATED_AT . ' field is required when updating an entity that uses timestamps');
            }
            $key_value = $this->getModelKeyValue($values);
            $found_records = $model->where($key_value)->get();
            // se ci sono proprietà che non sono nei fillable devo restituire errore?
            $discarded_values = $this->removeNonFillableProperties($model, $values->changes);

            // TODO: come gestire la preview del record? E se ci sono modifiche in pending cosa devo fare?
            // 1) impedisco la modifica finché non è approvato/disapprovato tutto
            // 2) posso mettere un flag "force" che disapprova le modifiche in pending e ne crea una nuova?


            if ($found_records->isEmpty() && $values->request->has('id')) {
                throw new ModelNotFoundException('No model Found');
            }
            $updated_records = new Collection();
            $this->db->transaction(function () use ($found_records, $updated_records, $values) {
                foreach ($found_records as $found_record) {
                    /** @psalm-suppress InvalidArgument */
                    if ($found_record->update($values->changes)) {
                        $updated_records->add($found_record->fresh());
                    }
                }
            });

            if ($discarded_values !== []) {
                $responseBuilder->setError($discarded_values);
            }

            return $responseBuilder
                ->setData($updated_records)
                ->getResponse();
        });
    }

    /**
     * @route-comment
     * Route(path: 'api/v1/delete/{entity}', name: 'core.api.delete', methods: [DELETE, POST], middleware: [api])
     * Route(path: 'app/crud/delete/{entity}', name: 'core.crud.delete', methods: [DELETE, POST], middleware: [web])
     */
    public function delete(ModifyRequest $request): Response
    {
        // delete deve bypassare le preview
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, ModifyRequestData $filters): Response {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'forceDelete', $model->getConnectionName());
            $key_value = $this->getModelKeyValue($filters);
            $found_records = $model->where($key_value)->get();

            if ($found_records->isEmpty() && $filters->request->has('id')) {
                throw new ModelNotFoundException('No model Found');
            }
            $deleted_records = new Collection();
            $this->db->transaction(function () use ($found_records, $deleted_records) {
                foreach ($found_records as $found_record) {
                    if ($found_record->forceDelete()) {
                        $deleted_records->add($found_record);
                    }
                }
            });

            return $responseBuilder
                ->setData($deleted_records)
                ->getResponse();
        });
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
        // activate deve bypassare le preview
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, ModifyRequestData $filters) use ($operation): Response {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'restore', $model->getConnectionName());
            $key = $filters->primaryKey;
            $key_value = is_string($key) ? $filters->$key : array_map(fn($k) => $filters->$k, $key);
            $found_record = $model->withTrashed()->findOrFail($key_value);
            if ($operation === 'activate' && !$found_record->restore()) {
                throw new \LogicException('Record not activated');
            }
            if (!$found_record->delete()) {
                throw new \LogicException('Record not inactivated');
            }

            $found_record->fresh();

            return $responseBuilder
                ->setData($found_record)
                ->getResponse();
        });
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
     * @param  "approve"|"disapprove"  $operation
     */
    private function doApproveOperation(ModifyRequest $request, string $operation): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, ModifyRequestData $filters) use ($operation): Response {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'approve', $model->getConnectionName());
            /** @var string|array $key */
            $key = $model->getKeyName();
            $key_value = is_string($key) ? $filters->$key : array_map(fn($k) => $filters->$k, $key);
            $found_record = $model->withTrashed()->findOrFail($key_value);
            /** @var User $user */
            $user = $this->auth->user();
            if ($filters['modification']) {
                $modification = Modification::query()->where(['modifiable_type' => $model::class, 'modifiable_id' => $filters->primaryKey])->findOrFail($filters['modification']);
                if ($operation === 'approve') {
                    $user->approve($modification);
                } else {
                    $user->disapprove($modification);
                }
            } else {
                $modifications = $model::query()->findOrFail($filters->primaryKey)->modifications()->activeOnly()->oldest()->cursor();
                if ($modifications->isEmpty()) {
                    throw new \LogicException("No modifications to be {$operation}d");
                }
                foreach ($modifications as $modification) {
                    if ($operation === 'approve') {
                        $user->approve($modification);
                    } else {
                        $user->disapprove($modification);
                    }
                }
            }

            $found_record->refresh();

            return $responseBuilder
                ->setData($found_record)
                ->getResponse();
        });
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/approve/{entity}', name: 'core.crud.approve', methods: [PATCH], middleware: [web])
     */
    public function approve(ModifyRequest $request): Response
    {
        return $this->doApproveOperation($request, 'approve');
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/disapprove/{entity}', name: 'core.crud.disapprove', methods: [PATCH], middleware: [web])
     */
    public function disapprove(ModifyRequest $request): Response
    {
        return $this->doApproveOperation($request, 'disapprove');
    }

    /**
     * @param  "lock"|"unlock"  $operation
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     */
    private function doLockOperation(ModifyRequest $request, string $operation): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, ModifyRequestData $filters) use ($operation): Response {
            $model = $filters->model;
            if (!class_uses_trait($model, HasLocks::class)) {
                throw new BadMethodCallException($model::class . ' doesn\'t support locks');
            }
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'lock', $model->getConnectionName());
            $key_value = $this->getModelKeyValue($filters);
            /** @var Model&HasLocks $found_records */
            $found_records = $model->where($key_value)->get();

            if ($found_records->isEmpty() && $filters->request->has('id')) {
                throw new ModelNotFoundException('No model Found');
            }
            $can_be_done = ($operation === 'lock' && $found_records->first()->isLocked()) || !$found_records->first()->isLocked();
            if ($found_records->count() === 1 && $filters->request->has('id') && $can_be_done) {
                throw new AlreadyLockedException($operation === 'lock' ? 'Record already locked' : 'Record isn\'t locked');
            }
            $locked_records = new Collection();
            $this->db->transaction(function () use ($found_records, $locked_records) {
                foreach ($found_records as $found_record) {
                    /** @psalm-suppress InvalidArgument */
                    if (!$found_record->isLocked() && $found_record->lock()) {
                        $locked_records->add($found_record->fresh());
                    }
                }
            });

            return $responseBuilder
                ->setData($locked_records)
                ->getResponse();
        });
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/lock/{entity}', name: 'core.crud.lock', methods: [PATCH], middleware: [web])
     */
    public function lock(ModifyRequest $request): Response
    {
        return $this->doLockOperation($request, 'lock');
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/unlock/{entity}', name: 'core.crud.unlock', methods: [PATCH], middleware: [web])
     */
    public function unlock(ModifyRequest $request): Response
    {
        return $this->doLockOperation($request, 'unlock');
    }

    /**
     * @route-comment
     * Route(path: 'app/crud/cache-clear/{entity}', name: 'core.crud.cache-clear', methods: [DELETE], middleware: [web])
     */
    public function clearModelCache(Request $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, CrudRequestData $filters): Response {
            $model = $filters->model;
            $table = $model->getTable();
            Cache::clearByEntity($model);

            return $responseBuilder
                ->setData("$table cached cleared")
                ->setStatus(Response::HTTP_OK)
                ->getResponse();
        });
    }

    //endregion
}
