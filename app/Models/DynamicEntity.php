<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Modules\Core\Exceptions\AmbiguousModelException;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Inspector\Entities\Column;
use Modules\Core\Inspector\Entities\ForeignKey;
use Modules\Core\Inspector\Entities\Index;
use Modules\Core\Inspector\Entities\Table;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Overrides\Model;
use Modules\Core\Services\DynamicEntityService;
use Override;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use UnexpectedValueException;

/**
 * @property string|Collection<int, string> $primaryKey
 * @mixin \Eloquent
 * @mixin IdeHelperDynamicEntity
 */
final class DynamicEntity extends Model
{
    use HasGridUtils;

    // no need to override table here, it's dynamically injected by code

    /**
     * @var bool
     */
    #[Override]
    public $timestamps = false;

    /**
     * @var array<string, array{type: string, foreignKey: ForeignKey}>
     */
    private array $dynamic_relations = [];

    /**
     * @var array<string, string>
     */
    private array $dynamic_casts = [];

    /**
     * @var array<string, array<string, array<int, mixed>>> Rules built from schema inspection (e.g. ['always' => ['name' => ['required']]])
     */
    private array $inspected_rules = [];

    /**
     * Returns a DynamicEntity for the table or a concrete Model (e.g. User) when one exists.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws \Exception
     */
    public static function resolve(string $tableName, ?string $connection = null, array $attributes = [], ?Request $request = null, ?string $module = null): EloquentModel
    {
        return DynamicEntityService::getInstance()->resolve($tableName, $connection, $attributes, $request, $module);
    }

    /**
     * @psalm-suppress MoreSpecificReturnType
     *
     * @throws InvalidArgumentException
     * @throws DirectoryNotFoundException
     * @throws \Exception
     *
     * @return class-string<EloquentModel>|null
     */
    public static function tryResolveModel(string $requestEntity, ?string $requestConnection = null, ?string $module = null): ?string
    {
        $models = models();

        $found = self::findModel($models, $requestEntity, $module);
        $found ??= self::findModel($models, Str::singular($requestEntity), $module);

        if (in_array($found, [null, '', '0'], true)) {
            return null;
        }

        $instance = new $found();
        $connection = $instance->getConnectionName();

        // When request does not specify a connection, accept the model (use default connection).
        return $requestConnection === null || $connection === $requestConnection ? $found : null;
    }

    /**
     * When fillable was not populated by inspect() (e.g. getInspectedTable returned null), derive from schema.
     */
    public function getFillable(): array
    {
        $fillable = parent::getFillable();

        if ($fillable !== [] || $this->table === '' || $this->table === 'dynamic_entities') {
            return $fillable;
        }

        $inspected = DynamicEntityService::getInstance()->getInspectedTable($this->getTable(), $this->getConnectionName());

        if (! $inspected instanceof Table) {
            return $fillable;
        }

        $names = [];

        foreach ($inspected->columns as $column) {
            if (! $column->isAutoincrement()) {
                $names[] = $column->name;
            }
        }

        $this->fillable = array_merge($this->fillable, $names);

        return $this->fillable;
    }

    /**
     * @return array<string, array{type: string, foreignKey: ForeignKey}>
     */
    public function getDynamicRelations(): array
    {
        return $this->dynamic_relations;
    }

    public function inspect(string $tableName, ?string $connection = null, ?Request $request = null): void
    {
        $this->setTableConnectionInfo($tableName, $connection);
        $this->verifyTableExistence();

        // Use service to get inspected table with in-memory caching
        $inspected = DynamicEntityService::getInstance()->getInspectedTable($this->getTable(), $this->getConnectionName());

        /** @var Table $inspected */
        $primary_key = $inspected->primaryKey;

        if ($primary_key instanceof Index) {
            $this->setPrimaryKeyInfo($primary_key, $inspected->getPrimaryKeyColumns());
        }

        $this->setDirectRelationsInfo($inspected->foreignKeys);

        if ($request instanceof Request) {
            $this->setReverseRelationsInfo($request);
        }

        $this->setColumnsInfo($inspected->columns, $inspected->foreignKeys, $inspected->indexes);
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function jsonSerialize(): array
    {
        $serialized = $this->toArray();

        // removing hashed values from json_encode
        return array_filter($serialized, static fn (mixed $v): bool => gettype($v) !== 'string' || ! (mb_strlen($v) === 60 && preg_match('/^\$2y\$/', $v)));
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(): array
    {
        $rules = parent::getRules();

        if ($this->inspected_rules === []) {
            return $rules;
        }

        $parent_default_rules = $rules[Model::DEFAULT_RULE] ?? [];
        if (! is_array($parent_default_rules)) {
            $parent_default_rules = [];
        }

        $rules[Model::DEFAULT_RULE] = array_merge(
            $parent_default_rules,
            $this->inspected_rules[Model::DEFAULT_RULE] ?? [],
        );

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return $this->dynamic_casts;
    }

    /**
     * @param  list<class-string<EloquentModel>>  $models
     *
     * @throws \Exception
     *
     * @return class-string<EloquentModel>|null
     */
    private static function findModel(array $models, string $modelName, ?string $module = null): ?string
    {
        $found = [];

        foreach ($models as $class) {
            if (Str::endsWith($class, '\\' . Str::studly($modelName))) {
                $found[] = $class;
            }
        }

        if ($module !== null && $module !== '') {
            $filtered = [];

            foreach ($found as $class) {
                if (class_module($class) === $module) {
                    $filtered[] = $class;
                }
            }

            $found = $filtered;
        }

        if (count($found) > 1) {
            $expected_basename = Str::studly($modelName);

            /** @var array<string, array<string, mixed>> $auth_providers */
            $auth_providers = config('auth.providers', []);

            foreach ($auth_providers as $provider) {
                $model = $provider['model'] ?? null;

                if (! is_string($model)) {
                    continue;
                }

                if (! class_exists($model)) {
                    continue;
                }

                if ($expected_basename === class_basename($model) && in_array($model, $found, true) && ($module === null || $module === class_module($model))) {
                    return $model;
                }
            }
        }

        throw_if(count($found) > 1, AmbiguousModelException::class, sprintf("Too many models found for '%s'", $modelName));

        return count($found) === 1 ? head($found) : null;
    }

    /**
     * Map Inspector/Doctrine column type to a Laravel validation rule (Laravel has no "unknown", "blob", etc.).
     */
    private function columnTypeToValidationRule(string $typeValue): string
    {
        $laravel_rules = ['string', 'integer', 'numeric', 'boolean', 'array', 'date', 'json'];

        return in_array($typeValue, $laravel_rules, true) ? $typeValue : 'string';
    }

    private function verifyTableExistence(): void
    {
        $table = $this->getTable();
        $connection = $this->getConnectionName();

        throw_unless(
            SchemaInspector::getInstance()->hasTable($table, $connection),
            UnexpectedValueException::class,
            sprintf("Table '%s' doesn't exists on '%s' connection", $table, $connection ?? 'default'),
        );
    }

    private function setTableConnectionInfo(string $tableName, ?string $connection = null): void
    {
        $this->setTable($tableName);

        if (! in_array($connection, [null, '', '0'], true)) {
            $this->setConnection($connection);
        }
    }

    /**
     * @param  Collection<int, Column>  $primaryKeyColumns
     */
    private function setPrimaryKeyInfo(Index $primaryKeyIndex, Collection $primaryKeyColumns): void
    {
        if ($primaryKeyColumns->count() > 1) {
            $this->primaryKey = $primaryKeyIndex->columns;
            $this->keyType = 'string';
            $this->incrementing = false;

            return;
        }

        $first_column = $primaryKeyColumns->first();

        if (! $first_column instanceof Column) {
            return;
        }

        $this->primaryKey = $first_column->name;
        $this->incrementing = $first_column->isAutoincrement();
        $this->keyType = $first_column->type->value && ! Str::contains($first_column->type->value, 'int') ? 'string' : 'int';
    }

    /**
     * @param  Collection<int, Column>  $columns
     * @param  Collection<int, ForeignKey>  $foreignKeys
     * @param  Collection<int, Index>  $indexes
     */
    private function setColumnsInfo(Collection $columns, Collection $foreignKeys, Collection $indexes): void
    {
        foreach ($columns as $column) {
            $this->setColumnInfo($column, $foreignKeys, $indexes);
        }
    }

    /**
     * @param  Collection<int, ForeignKey>  $foreignKeys
     * @param  Collection<int, Index>  $indexes
     */
    private function setColumnInfo(Column $column, Collection $foreignKeys, Collection $indexes): void
    {
        /** @var array<string, array{0: string, 1: string}> $remapped_fks */
        $remapped_fks = [];

        foreach ($foreignKeys as $fk) {
            $foreign_column_names = $fk->foreignColumnNames()->values()->all();

            foreach ($fk->localColumnNames()->values()->all() as $idx => $local_column) {
                if (! is_string($local_column)) {
                    continue;
                }

                $foreign_column = $foreign_column_names[$idx] ?? null;

                if (! is_string($foreign_column)) {
                    continue;
                }

                $remapped_fks[$local_column] = [$fk->foreignTable, $foreign_column];
            }
        }

        /** @var list<string> $remapped_uids */
        $remapped_uids = [];

        foreach ($indexes as $idx) {
            if (count($idx->columns) !== 1 || ! ($idx->isPrimaryKey() || $idx->isUnique())) {
                continue;
            }

            $unique_column = $idx->columns->first();

            if (is_string($unique_column)) {
                $remapped_uids[] = $unique_column;
            }
        }

        // add to fillable if is not readonly or autoincrement
        if (! $column->isAutoincrement()) {
            $this->fillable[] = $column->name;
        }

        // set the correct cast
        $is_date = $column->type->value && Str::contains($column->type->value, 'date');

        $this->dynamic_casts[$column->name] = $column->type->value;

        // validations (persist in inspected_rules so getRules() can merge them)
        if (! isset($this->inspected_rules[Model::DEFAULT_RULE])) {
            $this->inspected_rules[Model::DEFAULT_RULE] = [];
        }
        $type_rule = $is_date ? 'date' : $this->columnTypeToValidationRule($column->type->value);
        $this->inspected_rules[Model::DEFAULT_RULE][$column->name] = [$type_rule];

        $soft_delete = in_array($column->name, ['deleted', 'deleted_at', 'deletedAt'], true) && $this->forceDeleting;

        if (! $column->isUnsigned()) {
            $this->inspected_rules[Model::DEFAULT_RULE][$column->name][] = 'min:0';
        }

        if (! $column->isNullable() && ! $column->isAutoincrement()) {
            $this->inspected_rules[Model::DEFAULT_RULE][$column->name][] = 'required';
        }

        if (array_key_exists($column->name, $remapped_fks)) {
            $foreign_key_target = $remapped_fks[$column->name];
            $connection_name = $this->getConnectionName() ?? 'default';
            $this->inspected_rules[Model::DEFAULT_RULE][$column->name][] = sprintf(
                'exists:%s.%s,%s',
                $connection_name,
                $foreign_key_target[0],
                $foreign_key_target[1],
            );
        }

        $table = $this->getTable();

        if ($table !== '' && in_array($column->name, $remapped_uids, true)) {
            $this->inspected_rules[Model::DEFAULT_RULE][$column->name][] = Rule::unique($table)->where(function ($query) use ($soft_delete): void { // @pest-ignore-type
                if ($soft_delete) {
                    $query->whereNull('deleted_at');
                }
            });
        }

        if (in_array($column->name, ['deleted', 'deleted_at', 'deletedAt'], true) && $this->forceDeleting) {
            $this->forceDeleting = false;
        }
    }

    /**
     * @param  Collection<int, ForeignKey>  $foreignKeys
     */
    private function setDirectRelationsInfo(Collection $foreignKeys): void
    {
        foreach ($foreignKeys as $fk) {
            $this->setDirectRelationInfo($fk);
        }
    }

    private function setDirectRelationInfo(ForeignKey $foreignKey): void
    {
        $this->dynamic_relations[$foreignKey->foreignTable] = [
            'type' => 'hasMany',
            'foreignKey' => $foreignKey,
        ];
    }

    /**
     * @throws \Exception
     */
    private function setReverseRelationsInfo(Request $request): void
    {
        $relations = $request->input('relations');

        if (! is_array($relations)) {
            return;
        }

        foreach ($relations as $relation_name) {
            if (! is_string($relation_name)) {
                continue;
            }

            $this->setReverseRelationInfo($relation_name);
        }
    }

    /**
     * @throws \Exception
     */
    private function setReverseRelationInfo(string $relationName): void
    {
        $resolved_model = self::resolve($relationName, $this->getConnectionName());

        if (! $resolved_model instanceof DynamicEntity) {
            return;
        }

        $reverse_relations = $resolved_model->getDynamicRelations();

        foreach ($reverse_relations as $relation => $relation_data) {
            if ($relation !== $this->getTable()) {
                continue;
            }

            $source_foreign_key = $relation_data instanceof ForeignKey
                ? $relation_data
                : $relation_data['foreignKey'];
            $table = $resolved_model->getTable();
            $this->dynamic_relations[$table] = [
                'type' => 'belongsToMany',
                'foreignKey' => new ForeignKey(
                    'reversed_' . $source_foreign_key->name,
                    $source_foreign_key->foreignColumns,
                    $source_foreign_key->localSchema,
                    $source_foreign_key->localConnection ?? $source_foreign_key->foreignTable,
                    $source_foreign_key->columns,
                    $resolved_model->getConnectionName() ?? '',
                    $table,
                ),
            ];

            break;
        }
    }
}
