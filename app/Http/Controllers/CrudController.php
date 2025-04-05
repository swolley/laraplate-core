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
use Modules\Core\Cache\Searchable;
use Modules\Core\Casts\WhereClause;
use Illuminate\Foundation\Auth\User;
use Modules\Core\Cache\CacheManager;
use Modules\Core\Casts\FiltersGroup;
use Approval\Traits\RequiresApproval;
use Modules\Core\Models\Modification;
use Modules\Core\Casts\FilterOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Modules\Core\Casts\CrudRequestData;
use Modules\Core\Casts\ListRequestData;
use Modules\Core\Casts\TreeRequestData;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Database\DatabaseManager;
use Modules\Core\Casts\IParsableRequest;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Casts\DetailRequestData;
use Modules\Core\Casts\ModifyRequestData;
use Modules\Core\Casts\SearchRequestData;
use Modules\Core\Casts\SelectRequestData;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Locking\Traits\HasLocks;
use Modules\Core\Casts\HistoryRequestData;
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
use Modules\Core\Helpers\HasCrudOperations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Auth\AuthManager;
use Illuminate\Cache\Repository;

class CrudController extends Controller
{
    use HasCrudOperations;

    public function __construct(Repository $cache, AuthManager $auth, DatabaseManager $db)
    {
        parent::__construct($cache, $auth, $db);
    }

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
     * @return string|mixed[]
     */
    private function getModelKeyValue(CrudRequestData $filters): string|array
    {
        /** @var string|string[] $key */
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
     * List the specified resource
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     * @route-comment
     * Routes:
     * - GET|POST|HEAD api/v1/select/{entity}
     *   Name: core.api.list
     *   Middleware: api
     * - GET|POST|HEAD app/crud/select/{entity}
     *   Name: core.crud.list
     *   Middleware: web
     */
    public function list(ListRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, ListRequestData $filters): Response {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return CacheManager::tryByRequest($model, $filters->request, function () use ($model, $filters, $responseBuilder) {
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
            }, cache: $this->cache);
        });
    }

    /**
     * Show the specified resource
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    public function detail(DetailRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, DetailRequestData $filters): Response {
            $model = $filters->model;
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return CacheManager::tryByRequest($model, $filters->request, function () use ($model, $filters, $responseBuilder) {
                $query = $model::query();
                $crud_helper = new CrudHelper();
                $crud_helper->prepareQuery($query, $filters);

                return $responseBuilder
                    ->setClass($model)
                    ->setData($query->sole())
                    ->setCachedAt(Carbon::now());
            }, cache: $this->cache);
        });
    }

    private function getElasticSearchQuery(SearchRequestData $filters, ?array $embeddings = null): array
    {
        $templateKey = 'elastic_template:' . md5(serialize([$filters->filters, $embeddings]));

        $cache = Cache::store('redis'); // o altro store configurato per Octane
        if ($cachedTemplate = $cache->get($templateKey)) {
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
        $query = [];

        switch ($filter->operator) {
            case FilterOperator::EQUALS:
                $query = [
                    'term' => [
                        $filter->property => $filter->value,
                    ],
                ];
                break;
            case FilterOperator::NOT_EQUALS:
                $query = [
                    'bool' => [
                        'must_not' => [
                            'term' => [
                                $filter->property => $filter->value,
                            ],
                        ],
                    ],
                ];
                break;
            case FilterOperator::LIKE:
                $query = [
                    'wildcard' => [
                        $filter->property => '*' . $filter->value . '*',
                    ],
                ];
                break;
            case FilterOperator::NOT_LIKE:
                $query = [
                    'bool' => [
                        'must_not' => [
                            'wildcard' => [
                                $filter->property => '*' . $filter->value . '*',
                            ],
                        ],
                    ],
                ];
                break;
            case FilterOperator::IN:
                $query = [
                    'terms' => [
                        $filter->property => $filter->value,
                    ],
                ];
                break;
            case FilterOperator::GREAT:
                $query = [
                    'gt' => [
                        $filter->property => $filter->value,
                    ],
                ];
                break;
            case FilterOperator::GREAT_EQUALS:
                $query = [
                    'gte' => [
                        $filter->property => $filter->value,
                    ],
                ];
                break;
            case FilterOperator::LESS:
                $query = [
                    'lt' => [
                        $filter->property => $filter->value,
                    ],
                ];
                break;
            case FilterOperator::LESS_EQUALS:
                $query = [
                    'lte' => [
                        $filter->property => $filter->value,
                    ],
                ];
                break;
            case FilterOperator::BETWEEN:
                $query = [
                    'range' => [
                        $filter->property => [
                            'gte' => $filter->value[0],
                            'lte' => $filter->value[1],
                        ],
                    ],
                ];
                break;
        }

        return $query;
    }

    /**
     * @route-comment
     * Routes:
     * - GET|POST|HEAD api/v1/search/{entity?}
     *   Name: core.api.search
     *   Middleware: api
     * - GET|POST|HEAD app/crud/search/{entity?}
     *   Name: core.crud.search
     *   Middleware: web
     */
    public function search(SearchRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, SearchRequestData $filters): Response {
            $is_searchable_class = class_uses_trait($filters->model, Searchable::class);
            if (!isset($filters->model) || $is_searchable_class) {
                $embeddedDocument = null;

                if (property_exists($filters->model, 'embed') && $filters->model->embed !== null && !empty($filters->model->embed)) {
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
     * Show resource history
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     * @route-comment
     * Routes:
     * - GET|HEAD api/v1/history/{entity}
     *   Name: core.api.history
     *   Middleware: api
     * - GET|HEAD app/crud/history/{entity}
     *   Name: core.crud.history
     *   Middleware: web
     */
    public function history(HistoryRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, HistoryRequestData $filters): Response {
            $model = $filters->model;
            if (!$this->hasHistory($model)) {
                throw new BadMethodCallException("'$filters->mainEntity' doesn't have history handling");
            }
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return CacheManager::tryByRequest($model, $filters->request, function () use ($model, $filters, $responseBuilder) {
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
            }, cache: $this->cache);
        });
    }

    /**
     * Get the specified resource data
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     * @route-comment
     * Routes:
     * - GET|HEAD api/v1/tree/{entity}
     *   Name: core.api.tree
     *   Middleware: api
     * - GET|HEAD app/crud/tree/{entity}
     *   Name: core.crud.tree
     *   Middleware: web
     */
    public function tree(TreeRequest $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, TreeRequestData $filters): Response {
            $model = $filters->model;
            if (!$this->useRecursiveRelationships($model)) {
                throw new UnexpectedValueException("'$filters->mainEntity' is not a hierarchical class");
            }
            PermissionChecker::ensurePermissions($filters->request, $model->getTable(), 'select', $model->getConnectionName());

            return CacheManager::tryByRequest($model, $filters->request, function () use ($model, $filters, $responseBuilder) {
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
            }, cache: $this->cache);
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
     * Insert the specified resource
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     * @route-comment
     * Routes:
     * - POST api/v1/insert/{entity}
     *   Name: core.api.insert
     *   Middleware: api
     * - POST app/crud/insert/{entity}
     *   Name: core.crud.insert
     *   Middleware: web
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
     * Update the specified resource
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     * @route-comment
     * Routes:
     * - PATCH|PUT api/v1/update/{entity}
     *   Name: core.api.replace
     *   Middleware: api
     * - PATCH|PUT app/crud/update/{entity}
     *   Name: core.crud.replace
     *   Middleware: web
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
     * Remove the specified resource
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     * @route-comment
     * Routes:
     * - DELETE|POST api/v1/delete/{entity}
     *   Name: core.api.delete
     *   Middleware: api
     * - DELETE|POST app/crud/delete/{entity}
     *   Name: core.crud.delete
     *   Middleware: web
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
     * Logically restore the specified resource
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     * @route-comment
     * Route: PATCH app/crud/activate/{entity}
     * Name: core.crud.activate
     * Middleware: web
     */
    public function activate(ModifyRequest $request): Response
    {
        return $this->doActivateOperation($request, 'activate');
    }

    /**
     * Logically delete the specified resource
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     * @route-comment
     * Route: PATCH app/crud/inactivate/{entity}
     * Name: core.crud.inactivate
     * Middleware: web
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
     * Approve current pending record modifications
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     * @route-comment
     * Route: PATCH app/crud/approve/{entity}
     * Name: core.crud.approve
     * Middleware: web
     */
    public function approve(ModifyRequest $request): Response
    {
        return $this->doApproveOperation($request, 'approve');
    }

    /**
     * Register user disapprovation for pending record modifications
     *
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     * @route-comment
     * Route: PATCH app/crud/disapprove/{entity}
     * Name: core.crud.disapprove
     * Middleware: web
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
            /** @var Model&HasLocks */
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
     * Lock resource
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     * @route-comment
     * Route: PATCH app/crud/lock/{entity}
     * Name: core.crud.lock
     * Middleware: web
     */
    public function lock(ModifyRequest $request): Response
    {
        return $this->doLockOperation($request, 'lock');
    }

    /**
     * Unlock resource
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     * @route-comment
     * Route: PATCH app/crud/unlock/{entity}
     * Name: core.crud.unlock
     * Middleware: web
     */
    public function unlock(ModifyRequest $request): Response
    {
        return $this->doLockOperation($request, 'unlock');
    }

    /**
     * Clear model cache
     *
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws BindingResolutionException
     * @throws Throwable
     * @route-comment
     * Route: DELETE app/crud/cache-clear/{entity}
     * Name: core.crud.cache-clear
     * Middleware: web
     */
    public function clearModelCache(Request $request): Response
    {
        return $this->executeOperation($request, function (ResponseBuilder $responseBuilder, CrudRequestData $filters): Response {
            $model = $filters->model;
            $table = $model->getTable();
            CacheManager::clearByEntity($model, $this->cache);

            return $responseBuilder
                ->setData("$table cached cleared")
                ->setStatus(Response::HTTP_OK)
                ->getResponse();
        });
    }

    //endregion
}
