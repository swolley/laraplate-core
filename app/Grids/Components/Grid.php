<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Components;

use BadMethodCallException;
use Closure;
use Doctrine\DBAL\Exception as DBALException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\LazyCollection;
use Modules\Core\Cache\Repository as CacheRepository;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FiltersGroup;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Core\Cache\HasCache;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Grids\Casts\GridAction;
use Modules\Core\Grids\Casts\GridRequestData;
use Modules\Core\Grids\Definitions\Entity;
use Modules\Core\Grids\Exceptions\ConcurrencyException;
use Modules\Core\Grids\Hooks\HasReadHooks;
use Modules\Core\Grids\Hooks\HasWriteHooks;
use Modules\Core\Grids\Requests\GridRequest;
use Modules\Core\Grids\Resources\ResponseBuilder;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Helpers\ResponseBuilder as BaseResponseBuilder;
use Modules\Core\Inspector\SchemaInspector;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\UnknownClassOrInterfaceException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Throwable;
use UnexpectedValueException;

final class Grid extends Entity
{
    use HasReadHooks;
    use HasWriteHooks;

    public const string LAYOUTS_TABLE = 'grid_layouts';

    private bool $initialized = false;

    /**
     * @var array<int, Closure(Model): Field>
     */
    private array $prepareFieldsCallbacks = [];

    public function __construct(Model|string $model)
    {
        parent::__construct($model);
        $this->name = lcfirst($this->getModelName());
    }

    public static function useGridUtils(Model $model): bool
    {
        return class_uses_trait($model, HasGridUtils::class);
    }

    /**
     * @return array{fields: array<string, array<string, mixed>>}
     *
     * @throws \Exception
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws UnknownClassOrInterfaceException
     * @throws DBALException
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function getConfigs(): array
    {
        $this->checkGridConfigs();

        return $this->toArray();
    }

    // endregion [CONFIGS]

    // region [FIELDS]

    /**
     * alias of setFields for pipe.
     *
     * @param  Closure(Model): Field  ...$fields
     */
    public function fields(Closure ...$fields): void
    {
        $this->prepareFieldsCallbacks = array_values($fields);
    }

    // endregion [FIELDS]

    // region [VALIDATIONS]

    // endregion [VALIDATIONS]

    // region [EXECUTION]

    /**
     * start processing Grid request.
     */
    public function process(GridRequest|GridRequestData $request): JsonResponse
    {
        if ($request instanceof GridRequest) {
            $this->parseRequest($request);
        } else {
            $this->requestData = $request;
        }

        $this->checkGridConfigs();

        $http_request = $request instanceof GridRequest ? $request : $request->request;
        $response_builder = new ResponseBuilder($http_request);
        $response_builder->setAction($this->requestData->action->value);
        $response_builder->setPrimaryKey($this->getModel()->getKeyName());

        foreach (HasGridUtils::getTimestampColumns($this->getModel()) as $key => $value) {
            if (! is_string($key) || ! is_string($value)) {
                continue;
            }

            $method = 'set' . ucfirst((string) preg_replace('/At$/', '', $key));
            $response_builder->{$method}($value);
        }

        $action = $this->requestData->action;

        // TODO: solo imbastito, da testare
        if ($action !== GridAction::Check) {
            // if ($action !== GridAction::LAYOUT) {
            $this->prepareFields();
            // } else {
            // $this->prepareNecessaryFields();
            // }
        }

        if (GridAction::isReadAction($this->requestData->action)) {
            // validation rules
            if ($this->requestData->action === GridAction::Select) {
                /** @var array<string, array<string, mixed>> $all_rules */
                $all_rules = [];

                foreach ($this->getAllFields() as $name => $field) {
                    $rules = $field->getRules();

                    if ($rules !== []) {
                        $all_rules[$name] = $rules;
                    }
                }

                $response_builder->setRules($all_rules);
            }

            $response_builder = $this->processReadActions($response_builder, $request);
        } else {
            $response_builder = $this->processWriteActions($response_builder);
        }

        return $response_builder->json();
    }

    // region [CONFIGS]

    /**
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws BindingResolutionException
     */
    private function withTrashed(): bool
    {
        if (! $this->hasSoftDelete()) {
            return false;
        }

        $user = Auth::user();

        return $user && $user->can($this->getModel()->getTable() . '.delete');
    }

    /**
     * append column into grid configs fields and checks as forcedField.
     *
     * @param  array<string, array{readable: bool, writable: bool}|Closure(Model): Field>  $necessary_fields
     */
    private function appendFieldIntoList(array &$necessary_fields, string $column, bool $force = false): bool
    {
        if (! array_key_exists($column, $necessary_fields) && ! $this->hasFieldDeeply($column)) {
            $necessary_fields[$column] = Field::create($column);

            return true;
        }

        return false;
    }

    /**
     * complete grid properties with request data.
     *
     * @param  array<string, array{readable: bool, writable: bool}|Closure(Model): Field>  $necessary_fields
     *
     * @throws Exception
     * @throws ExpectationFailedException
     */
    private function completeFromRequestData(array &$necessary_fields): void
    {
        if (($columns = $this->requestData->columns) !== []) {
            foreach ($columns as $column) {
                $this->appendFieldIntoList($necessary_fields, $column->name, false);
            }
        }

        if (($relations = $this->requestData->relations) !== []) {
            foreach ($relations as $relation) {
                if (! $this->hasRelationDeeply($relation)) {
                    $relation_info = $this->resolveRelationshipDeeply($relation);

                    if ($relation_info !== false) {
                        $this->addRelationDeeply($relation_info);
                    }
                }
            }
        }

        if (($filters = $this->requestData->filters) instanceof \Modules\Core\Casts\FiltersGroup) {
            foreach (array_keys($filters->filters) as $column) {
                $this->appendFieldIntoList($necessary_fields, $column, true);
            }
        }

        if (($options = $this->requestData->optionsFilters) !== null && ($options = $this->requestData->optionsFilters) !== []) {
            foreach (array_keys($options) as $column) {
                $this->appendFieldIntoList($necessary_fields, $column, true);
            }
        }

        if (($funnels = $this->requestData->funnelsFilters) !== null && ($funnels = $this->requestData->funnelsFilters) !== []) {
            foreach (array_keys($funnels) as $column) {
                $this->appendFieldIntoList($necessary_fields, $column, true);
            }
        }

        if (($orders = $this->requestData->sort) !== []) {
            foreach ($orders as $order) {
                $this->appendFieldIntoList($necessary_fields, $order['property'], true);
            }
        }
    }

    /**
     * check grid configs and calls inject-fields methods.
     */
    private function checkGridConfigs(): void
    {
        try {
            if ($this->initialized) {
                return;
            }

            if (! $this->hasFieldsDeeply() && ! isset($this->requestData)) {
                $this->getDefaultModelFields();
            } else {
                $necessary_fields = [];

                if (isset($this->requestData)) {
                    $action = $this->requestData->action;

                    if ($action === GridAction::Check /* || $action === GridAction::LAYOUT */) {
                        return;
                    }

                    $this->completeFromRequestData($necessary_fields);
                }

                // force injection of necessary fields (primay, timestamps)
                if ($necessary_fields !== []) {
                    $this->addNecessaryFields($necessary_fields);
                }

                $necessary_fields = array_filter($necessary_fields, fn (Field|string $name): bool => ! $this->hasField($name), ARRAY_FILTER_USE_KEY);
                $this->initFieldsByConfigs($necessary_fields);
                /** @var array<string, Closure(Model): Field> $field_generators */
                $field_generators = $necessary_fields;
                $generated_fields = array_map(fn (Closure $generator): Field => $generator($this->getModel()), $field_generators);
                $this->setFields($generated_fields);
            }
        } finally {
            $this->initialized = true;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $options
     *
     * @throws DBALException
     * @throws \Exception
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws UnknownClassOrInterfaceException
     * @throws UnexpectedValueException
     */
    private function initOptions(array $options): void
    {
        foreach ($options as $column => $option_data) {
            if (! is_array($option_data)) {
                continue;
            }

            $field = $this->getFieldDeeply($column);

            if (! $field instanceof Field) {
                continue;
            }

            $prefix = $field->getPath();
            $label = $option_data['label'] ?? null;
            $model = $field->getModel();
            $option_columns = $option_data['columns'] ?? [$field->getName()];

            if (! is_array($option_columns)) {
                $option_columns = [$field->getName()];
            }

            if (! $label && $model instanceof Model) {
                $label = $this->checkColumnsOrGetDefaults($model, $field->getName(), $option_columns);
            }

            $field->options(Option::create(is_string($label) ? $label : null));
            $option = $field->getOption();

            if (! $option instanceof Option) {
                continue;
            }

            if (isset($option_data['columns']) && is_array($option_data['columns'])) {
                $additional_fields = [];

                foreach ($option_data['columns'] as $option_column) {
                    if (! is_string($option_column)) {
                        continue;
                    }

                    if (! Str::contains($option_column, '.')) {
                        $option_column = $prefix . '.' . $option_column;
                    }

                    $additional_fields[$option_column] = Field::create($option_column, writable: false);
                }

                $resolved_fields = [];

                foreach ($additional_fields as $field_name => $field_factory) {
                    $resolved_fields[$field_name] = $field_factory($this->getModel());
                }

                $option->addFields($resolved_fields);
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $funnels
     *
     * @throws DBALException
     * @throws \Exception
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws UnknownClassOrInterfaceException
     * @throws UnexpectedValueException
     */
    private function initFunnels(array $funnels): void
    {
        foreach ($funnels as $column => $funnel_data) {
            if (! is_array($funnel_data)) {
                continue;
            }

            $field = $this->getFieldDeeply($column);

            if (! $field instanceof Field) {
                continue;
            }

            $prefix = $field->getPath();
            $label = $funnel_data['label'] ?? null;
            $model = $field->getModel();
            $funnel_columns = $funnel_data['columns'] ?? [$field->getName()];

            if (! is_array($funnel_columns)) {
                $funnel_columns = [$field->getName()];
            }

            if (! $label && $model instanceof Model) {
                $label = $this->checkColumnsOrGetDefaults($model, $field->getName(), $funnel_columns);
            }

            $field->funnel(Funnel::create(is_string($label) ? $label : null));
            $funnel = $field->getFunnel();

            if (! $funnel instanceof Funnel) {
                continue;
            }

            if (isset($funnel_data['columns']) && is_array($funnel_data['columns'])) {
                $additional_fields = [];

                foreach ($funnel_data['columns'] as $funnel_column) {
                    if (! is_string($funnel_column)) {
                        continue;
                    }

                    if (! Str::contains($funnel_column, '.')) {
                        $funnel_column = $prefix . '.' . $funnel_column;
                    }

                    $additional_fields[$funnel_column] = Field::create($funnel_column, writable: false);
                }

                $resolved_fields = [];

                foreach ($additional_fields as $field_name => $field_factory) {
                    $resolved_fields[$field_name] = $field_factory($this->getModel());
                }

                $funnel->addFields($resolved_fields);
            }
        }
    }

    /**
     * inject all model fields if no configuration is set in controller.
     */
    private function getDefaultModelFields(): void
    {
        Log::debug('No configs for grid found, fallback to default injection');

        $all_fields = [];
        $this->addDefaultReadableFields($all_fields);
        $this->addDefaultWritableFields($all_fields);
        $this->addAppendFieldsToDefaults($all_fields);
        $this->addNecessaryFields($all_fields);
        $this->initFieldsByConfigs($all_fields);
        /** @var array<string, Closure(Model): Field> $field_generators */
        $field_generators = $all_fields;
        $generated_fields = array_map(fn (Closure $generator): Field => $generator($this->getModel()), $field_generators);
        $this->setFields($generated_fields);
    }

    /**
     * @return array<string, string>
     */
    private function getModelColumnTypes(): array
    {
        $model = $this->getModel();
        $hidden = property_exists($model, 'hidden') && is_array($model->hidden)
            ? array_values(array_filter($model->hidden, fn (mixed $column): bool => is_string($column)))
            : [];
        $fillable = property_exists($model, 'fillable') && is_array($model->fillable)
            ? array_values(array_filter($model->fillable, fn (mixed $column): bool => is_string($column)))
            : [];
        $columns = array_unique([...$hidden, ...$fillable]);
        $casts = $model->getCasts();
        $mapped_columns = [];

        foreach ($columns as $column) {
            $column_name = (string) $column;
            $cast_type = $casts[$column_name] ?? 'string';
            $mapped_columns[$column_name] = is_string($cast_type) ? $cast_type : 'string';
        }

        return $mapped_columns;
    }

    /**
     * @return array<int, string>
     */
    private function getModelAppendFields(): array
    {
        $model = $this->getModel();

        if (! property_exists($model, 'appends') || ! is_array($model->appends)) {
            return [];
        }

        return array_values(array_filter($model->appends, fn (mixed $name): bool => is_string($name)));
    }

    /**
     * @param  array<string, array{validation?: array<int, string>, readable: bool, writable: bool}>  $allFields
     */
    private function addAndScrollDefaultFields(array &$allFields, bool $readable): void
    {
        $fields = $this->getModelColumnTypes();

        foreach ($fields as $name => $type) {
            if (! is_string($name)) {
                continue;
            }

            if (array_key_exists($name, $allFields)) {
                $allFields[$name][$readable ? 'readable' : 'writable'] = true;
            } else {
                $allFields[$name] = ['validation' => [is_string($type) ? $type : 'string'], 'readable' => $readable, 'writable' => ! $readable];
            }
        }
    }

    /**
     * get all readable fields and appends configs to array of default fields.
     *
     * @param  array<string, array{validation?: array<int, string>, readable: bool, writable: bool}>  $allFields
     */
    private function addDefaultReadableFields(array &$allFields): void
    {
        $this->addAndScrollDefaultFields($allFields, true);
    }

    /**
     * get all writable fields and appends configs to array of default fields.
     *
     * @param  array<string, array{validation?: array<int, string>, readable: bool, writable: bool}>  $allFields
     */
    private function addDefaultWritableFields(array &$allFields): void
    {
        $this->addAndScrollDefaultFields($allFields, false);
    }

    /**
     * get all calculated fields in Model and appends configs to array of default fields.
     *
     * @param  array<string, array{validation?: array<int, string>, readable: bool, writable: bool}>  $allFields
     */
    private function addAppendFieldsToDefaults(array &$allFields): void
    {
        $appends = $this->getModelAppendFields();

        foreach ($appends as $name) {
            if (! is_string($name)) {
                continue;
            }
            if (! array_key_exists($name, $allFields)) {
                $allFields[lcfirst($this->getModelName()) . '.' . $name] = ['validation' => [], 'readable' => true, 'writable' => false];
            }
        }
    }

    /**
     * call addTimestampsFields and addPrimaryKey methods.
     *
     * @param  array<string, array{validation?: array<int, string>, readable: bool, writable: bool}|Closure(Model): Field>  $allFields
     */
    private function addNecessaryFields(array &$allFields): void
    {
        $this->addTimestampsFields($allFields);
        $this->addPrimaryKey($allFields);
    }

    /**
     * get all timestamps fields and appends configs to array of passed one.
     *
     * @param  array<string, array{validation?: array<int, string>, readable: bool, writable: bool}|Closure(Model): Field>  $allFields
     */
    private function addTimestampsFields(array &$allFields): void
    {
        $timestamps = array_filter($this->getTimestampsColumns(), fn (mixed $name): bool => is_string($name) && $name !== '');

        foreach ($timestamps as $name) {
            $allFields[lcfirst($this->getModelName()) . '.' . $name] = ['validation' => ['date', 'filled'], 'readable' => true, 'writable' => true];
        }
    }

    /**
     * get primary key field/s and appends configs to array of passed one.
     *
     * @param  array<string, array{validation?: array<int, string>, readable: bool, writable: bool}|Closure(Model): Field>  $allFields
     */
    private function addPrimaryKey(array &$allFields): void
    {
        $primary_key_name = Arr::wrap($this->getPrimaryKey());
        $primary_key_type = $this->getModel()->getKeyType();
        $is_autoincremental = $this->getModel()->incrementing;

        foreach ($primary_key_name as $name) {
            if (! is_string($name)) {
                continue;
            }

            $full_name = lcfirst($this->getModelName()) . '.' . $name;

            if (! array_key_exists($full_name, $allFields)) {
                $allFields[$full_name] = ['validation' => [$primary_key_type, 'filled'], 'readable' => true, 'writable' => ! $is_autoincremental];
            } elseif (is_array($allFields[$full_name])) {
                $allFields[$full_name]['readable'] = true;
                $allFields[$full_name]['writable'] = ! $is_autoincremental;
            }
        }
    }

    /**
     * @throws \Exception
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws UnknownClassOrInterfaceException
     * @throws UnexpectedValueException
     * @throws BindingResolutionException
     */
    private function prepareFields(): void
    {
        $fields = array_map(fn (callable $generator) => $generator($this->getModel()), $this->prepareFieldsCallbacks);
        $this->setFields($fields);
        $this->prepareNecessaryFields();
    }

    /**
     * @throws BindingResolutionException
     * @throws \Exception
     * @throws Exception
     * @throws ExpectationFailedException
     * @throws UnknownClassOrInterfaceException
     * @throws UnexpectedValueException
     */
    private function prepareNecessaryFields(): void
    {
        $necessary_fields = [];
        $this->addNecessaryFields($necessary_fields);

        foreach ($necessary_fields as $name => &$config) {
            if (! is_array($config)) {
                continue;
            }

            $config = Field::create($name, readable: $config['readable'], writable: $config['writable']);
        }

        unset($config);
        $fields = array_map(fn (Closure $generator): Field => $generator($this->getModel()), array_values($necessary_fields));
        $this->setFields($fields);
    }

    /**
     * call Field constructors from list of configs.
     *
     * @param  array<string, array{validation?: array<int, string>, readable: bool, writable: bool}|Closure(Model): Field>  $list
     */
    private function initFieldsByConfigs(array &$list): void
    {
        $model_class = lcfirst($this->getModelName());

        foreach ($list as $name => &$field_info) {
            $field_info = is_callable($field_info) ? $field_info : Field::create(Str::startsWith($name, $model_class) ? $name : sprintf('%s.%s', $model_class, $name), readable: $field_info['readable'], writable: $field_info['writable']);
        }
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     * @throws BadRequestException
     * @throws SuspiciousOperationException
     * @throws QueryException
     */
    private function callbackToReadAction(ResponseBuilder $responseBuilder, Request $request): ResponseBuilder
    {
        $action = $this->requestData->action;
        // if (!$action) {
        //     throw new \UnexpectedValueException('Unexpected action');
        // }

        // concurrency checks
        if ($action === GridAction::Check) {
            return $this->processConcurrencies($responseBuilder);
        }

        // layouts
        if ((/* $action === GridAction::LAYOUT && */ $request->getMethod() === Request::METHOD_GET)) {
            return $this->processLayouts($responseBuilder);
        }

        $processes = [];

        // data
        if (in_array($action, [GridAction::Select, GridAction::Data], true)) {
            $processes[] = fn (): ResponseBuilder => $this->processData($responseBuilder);
        }

        // options
        if ($action === GridAction::Select || $action === GridAction::Options) {
            $processes[] = fn (): ResponseBuilder => $this->processOptions($responseBuilder);
        }

        // funnels
        if ($action === GridAction::Select || $action === GridAction::Funnels) {
            $processes[] = fn (): ResponseBuilder => $this->processFunnels($responseBuilder);
        }

        if ($processes !== []) {
            $concurrency_driver = Concurrency::driver(App::runningInConsole() ? 'fork' : 'process');

            if (! is_object($concurrency_driver) || ! method_exists($concurrency_driver, 'run')) {
                throw new RuntimeException('Invalid concurrency driver');
            }

            $concurrency_driver->run($processes);
        }

        return $responseBuilder;
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     * @throws BadRequestException
     * @throws SuspiciousOperationException
     * @throws QueryException
     */
    private function processReadActions(ResponseBuilder $responseBuilder, GridRequest|GridRequestData $request): ResponseBuilder
    {
        $http_request = $request instanceof GridRequest ? $request : $request->request;

        if (class_uses_trait($this->getModel(), HasCache::class)) {
            $cache = Cache::getFacadeRoot();

            if (! $cache instanceof CacheRepository) {
                throw new RuntimeException('Invalid cache repository');
            }

            return $cache->tryByRequest($this->getModel(), $http_request, fn (): ResponseBuilder => $this->callbackToReadAction($responseBuilder, $http_request));
        }

        return $this->callbackToReadAction($responseBuilder, $http_request);
    }

    /**
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     * @throws BadRequestException
     */
    private function processData(ResponseBuilder $responseBuilder): ResponseBuilder
    {
        [$data, $total_records] = $this->getData();

        if ($data instanceof LazyCollection) {
            $data = $data->collect();
        }

        $this->setDataIntoResponse($responseBuilder, $data, $total_records);
        $responseBuilder->setClass($this->getModel());
        $responseBuilder->setTable($this->getModel()->getTable());

        return $responseBuilder;
    }

    private function processOptions(ResponseBuilder $responseBuilder): ResponseBuilder
    {
        $data = $this->getOptionsData();
        $responseBuilder->setOptions($data);

        return $responseBuilder;
    }

    private function processFunnels(ResponseBuilder $responseBuilder): ResponseBuilder
    {
        $responseBuilder->setFunnels($this->getFunnelsData());

        return $responseBuilder;
    }

    /**
     * @throws QueryException
     * @throws InvalidArgumentException
     */
    private function processLayouts(ResponseBuilder $responseBuilder): ResponseBuilder
    {
        $responseBuilder->setLayouts($this->getUserLayouts());

        return $responseBuilder;
    }

    /**
     * @throws InvalidArgumentException
     * @throws BindingResolutionException
     * @throws Throwable
     * @throws UnexpectedValueException
     */
    private function processConcurrencies(ResponseBuilder $responseBuilder): ResponseBuilder
    {
        $data = $this->checkRecordsConcurrency() ?? new Collection();

        $responseBuilder->setData($data);
        $responseBuilder->setTotalRecords($data->count());

        if ($data->isNotEmpty()) {
            $responseBuilder->setError(new ConcurrencyException());
        }

        return $responseBuilder;
    }

    /**
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws SuspiciousOperationException
     * @throws BadMethodCallException
     * @throws \Exception
     * @throws UnexpectedValueException
     * @throws BindingResolutionException
     * @throws Throwable
     */
    private function processWriteActions(ResponseBuilder $responseBuilder): ResponseBuilder
    {
        $action = $this->requestData->action;

        if ($action === GridAction::Insert) {
            $responseBuilder->setData($this->createRecord());
            $responseBuilder->setStatus(Response::HTTP_CREATED);

            return $responseBuilder;
        }

        if ($action === GridAction::Update) {
            $this->updateRecords();
        }

        if ($action === GridAction::Delete) {
            $this->softDeleteRecords();
        }

        if ($action === GridAction::ForceDelete) {
            $this->forceDeleteRecords();
        }

        throw new InvalidArgumentException('Not a valid action');
    }

    /**
     * @throws QueryException
     */
    private function checkGridLayoutsTableExists(): bool
    {
        $exists = SchemaInspector::getInstance()->hasTable(self::LAYOUTS_TABLE);

        if (! $exists) {
            Log::warning('No ' . self::LAYOUTS_TABLE . ' table found');
        }

        return $exists;
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function getUserLayouts(): Collection
    {
        if (! $this->checkGridLayoutsTableExists()) {
            return new Collection();
        }

        return DB::table(self::LAYOUTS_TABLE)->where(function (\Illuminate\Database\Query\Builder $query): void {
            $user = Auth::user();

            if ($user) {
                $query->where('user_id', $user->id);
            }

            $query->orWhere('is_public', true);
        })->where('grid_name', $this->requestData->layout['grid_name'])->get();
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function getEntityFilters(string $entity, array $filters): array
    {
        $matched = [];

        foreach ($filters as $key => $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $property = is_string($key) && str_contains($key, '.')
                ? $key
                : ($filter['property'] ?? null);

            if (! is_string($property) || preg_match('/^' . preg_quote($entity, '/') . "\.[a-zA-Z0-9_]+$/", $property) !== 1) {
                continue;
            }

            if (! isset($filter['property'])) {
                $filter['property'] = $property;
            }

            $matched[] = $filter;
        }

        return $matched;
    }

    /**
     * @param  Builder<Model>|EloquentRelation<*, *, *>  $query
     * @param  array<int, array<string, mixed>>  $filters
     * @param  Collection<string, Field>  $allFields
     */
    private function addWhereFiltersIntoQuery(Builder|EloquentRelation $query, array $filters, Collection $allFields): void
    {
        foreach ($filters as $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $property = $filter['property'] ?? null;

            if (! is_string($property)) {
                continue;
            }

            $field = $allFields->offsetExists($property) ? $allFields->offsetGet($property) : false;

            if ($field instanceof Field && array_key_exists('value', $filter)) {
                $operator_value = $filter['operator'] ?? '';
                $operator = is_string($operator_value)
                    ? FilterOperator::tryFrom($operator_value)
                    : null;

                if ($operator instanceof FilterOperator) {
                    $this->applyCorrectWhereMethodForQuery($query, $field, $operator, $filter['value'] === 'null' ? null : $filter['value']);
                }
            }
        }
    }

    /**
     * @return array{Collection<int, Model>|LazyCollection<int, Model>, int}
     */
    private function getData(): array
    {
        $all_fields = $this->getAllFields()->reject(fn (Field $f): bool => $f->isAppend());
        $request_columns = $this->requestData->columns;
        $main_columns = ($request_columns !== [] ? $this->getFields() : $all_fields)->map(fn (Field $f): string => $f->getName())->all();
        $columns_filters = $this->flattenRequestFilters($this->requestData->filters);
        $funnels_filters = [];

        foreach ($this->requestData->funnelsFilters ?? [] as $funnel_filter) {
            if (is_array($funnel_filter) && ($funnel_filter['value'] ?? []) !== []) {
                $funnels_filters[] = $funnel_filter;
            }
        }

        $model_filters = [
            ...$this->getEntityFilters(lcfirst($this->getModelName()), $columns_filters),
            ...$this->getEntityFilters(lcfirst($this->getModelName()), $funnels_filters),
        ];

        $query = $this->getModel()::query();
        $query->select(array_values($main_columns) !== [] ? array_values($main_columns) : ['*']);

        $this->addWhereFiltersIntoQuery($query, $model_filters, $all_fields);

        $all_relations = $this->getAllFullRelationsNames();

        foreach ($all_relations as $relation) {
            $relation_info = $this->getRelationDeeply($relation);

            if ($relation_info === null) {
                continue;
            }

            $query_columns = $query->getQuery()->columns ?? [];

            if (! in_array($relation_info->info->getForeignKey(), $query_columns, true)) {
                $query->addSelect($relation_info->info->getForeignKey());
            }

            $relation_filters = [
                ...$this->getEntityFilters($relation, $columns_filters),
                ...$this->getEntityFilters($relation, $funnels_filters),
            ];

            $real_relation_name = preg_replace('/^' . lcfirst($this->getModelName()) . "\./", '', $relation);

            if (! is_string($real_relation_name) || $real_relation_name === '') {
                continue;
            }

            $query->with($real_relation_name, function (EloquentRelation $relation_query) use ($relation_filters, $relation_info, $all_fields): void {
                $relation_builder = $relation_query->getQuery();
                $relation_columns = $relation_info->getFields()->map(fn (Field $f): string => $f->getName())->all();
                $relation_builder->select($relation_columns === [] ? ['*'] : array_values($relation_columns));

                $relation_query_columns = $relation_builder->getQuery()->columns ?? [];

                if (! in_array($relation_info->info->getOwnerKey(), $relation_query_columns, true)) {
                    $relation_builder->addSelect($relation_info->info->getOwnerKey());
                }

                $this->addWhereFiltersIntoQuery($relation_query, $relation_filters, $all_fields);
            });

            if ($relation_filters !== []) {
                $query->whereHas($real_relation_name, function (Builder $relation_query) use ($relation_filters, $all_fields): void {
                    $this->addWhereFiltersIntoQuery($relation_query, $relation_filters, $all_fields);
                });
            }
        }

        // count is calculated before pagination but after filters
        $count = $query->count();

        if ($this->requestData->pagination !== 0) {
            $query->skip((int) $this->requestData->from - 1);

            if ($this->requestData->to !== null) {
                $query->take($this->requestData->to - (int) $this->requestData->from + 1);
            }
        }

        if (($sorts = $this->requestData->sort) !== []) {
            $this->addSortsIntoQuery($query, $sorts);
        }

        // Use lazy loading for large datasets without pagination to reduce memory usage
        $data = $this->requestData->pagination === 0 && $count > 500 ? $query->lazy() : $query->get();

        return [$data, $count];
    }

    /**
     * @return Collection<string, BaseResponseBuilder>|null
     */
    private function getFunnelsData(): ?Collection
    {
        $request_funnels = $this->requestData->funnelsFilters;

        if ($request_funnels === null || $request_funnels === []) {
            return null;
        }

        /** @var Collection<string, BaseResponseBuilder> $funnels */
        $funnels = new Collection();

        foreach (array_keys($request_funnels) as $name) {
            if (! is_string($name)) {
                continue;
            }

            $field = $this->getFieldDeeply($name);

            if (! $field instanceof Field) {
                continue;
            }

            $funnel = $field->getFunnel();

            if (! $funnel instanceof Funnel) {
                continue;
            }

            $response = $funnel->process($this->requestData);

            if ($response->isNotEmpty()) {
                $funnels->offsetSet($name, $response);
            }
        }

        return $funnels;
    }

    /**
     * @return Collection<string, BaseResponseBuilder>|null
     */
    private function getOptionsData(): ?Collection
    {
        $request_options = $this->requestData->optionsFilters;

        if ($request_options === null || $request_options === []) {
            return null;
        }

        /** @var Collection<string, BaseResponseBuilder> $options */
        $options = new Collection();

        foreach (array_keys($request_options) as $name) {
            if (! is_string($name)) {
                continue;
            }

            $field = $this->getFieldDeeply($name);

            if (! $field instanceof Field) {
                continue;
            }

            $option = $field->getOption();

            if (! $option instanceof Option) {
                continue;
            }

            $response = $option->process($this->requestData);

            if ($response->isNotEmpty()) {
                $options->offsetSet($name, $response);
            }
        }

        return $options;
    }

    /**
     * @return Collection<int, Model>|null
     */
    private function checkRecordsConcurrency(): ?Collection
    {
        $request_changes = $this->requestData->changes;

        if ($request_changes === []) {
            return null;
        }

        $primary_key_fields = $this->getModel()->getKeyName();

        if (! is_array($primary_key_fields)) {
            $primary_key_fields = [$primary_key_fields];
        }

        $query = $this->newModelQuery($this->hasSoftDelete());

        $fields = [];

        if ($request_changes !== null) {
            foreach ($request_changes as $record) {
                $query->orWhere(function (Builder $q) use ($record, $primary_key_fields, &$fields): void {
                    throw_unless(is_array($record['record']), InvalidArgumentException::class, 'Invalid record value');

                    throw_if($primary_key_fields !== array_keys($record['record']), InvalidArgumentException::class, 'Invalid primary key fields');

                    $this->applyCorrectWhereMethodForQuery($q, $record['property'], FilterOperator::NotEquals, $record['value']);

                    foreach ($record['record'] as $column => $value) {
                        $this->applyCorrectWhereMethodForQuery($q, $column, FilterOperator::Equals, $value);
                    }

                    if ($fields === []) {
                        array_push($fields, ...array_keys($record['record']));
                        $fields[] = $record['property'];
                    }
                });
            }
        }

        $query->select($fields === [] ? ['*'] : $fields);

        return $query->get();
    }

    // private function getRecordFromPrimaryKey(): \Illuminate\Database\Eloquent\Collection
    // {
    // 	$query = $this->model::query();
    // 	if (is_array($full_primary_key)) {
    // 		$key_value = [];
    // 		$records_to_find = 0;
    // 		foreach ($full_primary_key as $full_pk) {
    // 			$key_value[$full_pk] = $this->getPrimaryKeyFromRequest($request, $full_pk, $writeType);
    // 			$records_to_find =  max($records_to_find, $this->putIntoWhere($this->getPrimaryKeyFromRequest($request, $full_pk, $writeType), $primary_key, $query));
    // 		}
    // 	} else {
    // 		$records_to_find = $this->putIntoWhere($this->getPrimaryKeyFromRequest($request, $full_primary_key, $writeType), $primary_key, $query);
    // 	}
    // 	if ($this->withTrashed && in_array(SoftDeletes::class, class_uses_recursive($query->getModel()::class))) $query->withTrashed();
    // 	/**
    // 	 *@var \Illuminate\Database\Eloquent\Collection
    // 	 */
    // 	$items_found = $query->sharedLock()->get();
    // 	$records_total = $items_found->count();
    // 	if ($records_total  != $records_to_find) {
    // 		Log::warning("Records to {$writeType->value} $records_to_find but found $records_total on recordset: " . json_encode($request->all()));
    // 		throw new \Exception("Records to {$writeType->value} " . $records_to_find . " but found " . $records_total);
    // 	}
    // 	return $items_found;
    // }

    private function createRecord(): bool
    {
        // DB::beginTransaction();
        $primary_key = $this->getPrimaryKey();
        $primary_value = is_string($primary_key) ? $this->requestData->request->get($primary_key) : array_map($this->requestData->request->get(...), $primary_key);

        throw_if($this->getModel()->incrementing && $primary_value !== null && $primary_value !== [], UnexpectedValueException::class, 'Cannot assign a value to the primary key because is an autoincrement field');
        $full_primary_key = $this->getFullPrimaryKey();
        $data = array_filter($this->requestData->request->all(), fn (string|int $k): bool => (is_array($full_primary_key) ? ! in_array($k, $full_primary_key, true) : $k !== $full_primary_key) && $k !== 'action', ARRAY_FILTER_USE_KEY);
        // $validator = Validator::make($data, $this->getAllFields()->getRules);
        // $validated = $validator->stopOnFirstFailure(false)->validate();

        if ($callback = $this->onPreInsert()) {
            $data = $callback($data);
        }

        if ($data === false) {
            Log::info('Insert blocked by preInsert hook');

            return false;
        }

        if ($created = $this->getModel()->insert($data)) {
            // $records_total = $this->setModifiedData(collect([$created]), Response::HTTP_CREATED);
            // DB::commit();
        }

        // DB::rollBack();
        // $this->setNotModifiedData();

        throw new BadMethodCallException('Not implemented');
    }

    // private function getPrimaryKeyFromRequest(Request $request, string $key)
    // {
    // 	$key_value = [];
    // 	$primaryKey = $request->get('primaryKey');
    // 	if (isset($primaryKey) && is_array($primaryKey))
    // 		if (count($primaryKey) == 1) $key_value = $primaryKey[0][$key];
    // 		else
    // 			foreach ($primaryKey as $record) {
    // 				$key_value[] = $record[$key];
    // 			}
    // 	if (!$key_value || $key_value == []) {
    // 		$message = "Primary key '$key' is mandatory in " . $this->requestData->action . " requests";
    // 		Log::warning($message);
    // 		throw new \\UnexpectedValueException($message);
    // 	}
    // 	return $key_value;
    // }

    private function updateRecords(): never
    {
        $primary_key = $this->getPrimaryKey();
        $primary_value = is_string($primary_key) ? $this->requestData->request->get($primary_key) : array_map($this->requestData->request->get(...), $primary_key);
        $query = $this->newModelQuery($this->withTrashed());
        $query->findOrFail($primary_value);

        throw new BadMethodCallException('Not implemented');
    }

    private function softDeleteRecords(): never
    {
        throw new BadMethodCallException('Not implemented');
    }

    private function forceDeleteRecords(): never
    {
        throw new BadMethodCallException('Not implemented');
    }

    /**
     * @return Builder<Model>
     */
    private function newModelQuery(bool $withTrashed = false): Builder
    {
        $model_class = $this->getModel()::class;

        if ($withTrashed && in_array(SoftDeletes::class, class_uses_recursive($model_class), true)) {
            return $model_class::withTrashed();
        }

        return $model_class::query();
    }

    /**
     * @param  Builder<Model>|EloquentRelation<*, *, *>  $query
     */
    private function applyCorrectWhereMethodForQuery(Builder|EloquentRelation $query, Field|string $field, FilterOperator $operator, mixed $value): void
    {
        if ($query instanceof EloquentRelation) {
            self::applyCorrectWhereMethod($query->getQuery(), $field, $operator, $value);

            return;
        }

        self::applyCorrectWhereMethod($query, $field, $operator, $value);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function flattenRequestFilters(?FiltersGroup $filters): array
    {
        if (! $filters instanceof FiltersGroup) {
            return [];
        }

        $flat_filters = [];

        foreach ($filters->filters as $key => $filter) {
            if ($filter instanceof Filter) {
                if ($filter->value === '') {
                    continue;
                }

                $flat_filters[] = [
                    'property' => $filter->property,
                    'value' => $filter->value,
                    'operator' => $filter->operator->value,
                ];

                continue;
            }

            if (is_array($filter) && ($filter['value'] ?? '') !== '') {
                $property = $filter['property'] ?? (is_string($key) ? $key : null);

                if (is_string($property)) {
                    $filter['property'] = $property;
                    $flat_filters[] = $filter;
                }
            }
        }

        return $flat_filters;
    }

    /**
     * @return array<int, \Modules\Core\Grids\Definitions\RelationInfo>|false
     */
    private function resolveRelationshipDeeply(string $relation): array|false
    {
        if (! Grid::useGridUtils($this->getModel())) {
            return false;
        }

        $model_class = $this->getFullModelName();

        /** @var callable(string): (array<int, \Modules\Core\Grids\Definitions\RelationInfo>|false) $resolver */
        $resolver = [$model_class, 'getRelationshipDeeply'];

        return $resolver($relation);
    }

    // endregion [EXECUTION]
}
