<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Inspector\Entities\Column;
use Modules\Core\Inspector\Entities\ForeignKey;
use Modules\Core\Inspector\Entities\Index;
use Modules\Core\Inspector\Entities\Table;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Services\DynamicEntityService;
use Override;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use UnexpectedValueException;

/**
 * @mixin IdeHelperDynamicEntity
 */
final class DynamicEntity extends Model
{
    use HasFactory;
    use HasGridUtils;
    use HasValidations {
        getRules as private getRulesTrait;
    }
    use HasVersions;
    use SoftDeletes;

    /**
     * @var bool
     */
    #[Override]
    public $timestamps = false;

    private array $dynamic_relations = [];

    private array $dynamic_casts = [];

    /** @var array<string, array<string, array<int, mixed>>> Rules built from schema inspection (e.g. ['always' => ['name' => ['required']]]) */
    private array $inspected_rules = [];

    /**
     * Returns a DynamicEntity for the table or a concrete Model (e.g. User) when one exists.
     *
     * @throws Exception
     */
    public static function resolve(string $tableName, ?string $connection = null, $attributes = [], ?Request $request = null): Model
    {
        return DynamicEntityService::getInstance()->resolve($tableName, $connection, $attributes, $request);
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
     * @psalm-suppress MoreSpecificReturnType
     *
     * @throws InvalidArgumentException
     * @throws DirectoryNotFoundException
     * @throws Exception
     *
     * @return class-string<Model>|null
     */
    public static function tryResolveModel(string $requestEntity, ?string $requestConnection = null): ?string
    {
        $models = models();
        $found = self::findModel($models, $requestEntity);

        $found ??= self::findModel($models, Str::singular($requestEntity));

        if (in_array($found, [null, '', '0'], true)) {
            return null;
        }

        $instance = new $found();
        $connection = $instance->getConnectionName();

        // When request does not specify a connection, accept the model (use default connection).
        return $requestConnection === null || $connection === $requestConnection ? $found : null;
    }

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

        if (! $inspected instanceof Table) {
            return;
        }

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

    #[Override]
    public function jsonSerialize(): array
    {
        $serialized = $this->toArray();

        // removing hashed values from json_encode
        return array_filter($serialized, static fn ($v): bool => gettype($v) !== 'string' || ! (mb_strlen($v) === 60 && preg_match('/^\$2y\$/', $v)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return $this->dynamic_casts;
    }

    /**
     * @throws Exception
     *
     * @return class-string<Model>|null
     */
    private static function findModel(array $models, string $modelName): ?string
    {
        $found = array_filter($models, fn ($c) => Str::endsWith($c, '\\' . Str::studly($modelName)));

        throw_if(count($found) > 1, Exception::class, sprintf("Too many models found for '%s'", $modelName));

        return count($found) === 1 ? head($found) : null;
    }

    private function verifyTableExistence(): void
    {
        throw_unless(
            SchemaInspector::getInstance()->hasTable($this->table, $this->connection),
            UnexpectedValueException::class,
            sprintf("Table '%s' doesn't exists on '%s' connection", $this->table, $this->connection),
        );
    }

    private function setTableConnectionInfo(string $tableName, ?string $connection = null): void
    {
        $this->setTable($tableName);

        if (! in_array($connection, [null, '', '0'], true)) {
            $this->setConnection($connection);
        }
    }

    private function setPrimaryKeyInfo(Index $primaryKeyIndex, Collection $primaryKeyColumns): void
    {
        if ($primaryKeyColumns->count() > 1) {
            $this->primaryKey = $primaryKeyIndex->columns;
            $this->keyType = 'string';
            $this->incrementing = false;
        } else {
            $first_column = $primaryKeyColumns->first();

            if ($first_column) {
                $this->primaryKey = $first_column->name;
                $this->incrementing = $first_column->isAutoincrement();
                $this->keyType = $first_column->type->value && ! Str::contains($first_column->type->value, 'int') ? 'string' : 'int';
            }
        }
    }

    /**
     * @param  Collection<Column>  $columns
     * @param  Collection<ForeignKey>  $foreignKeys
     * @param  Collection<Index>  $indexes
     */
    private function setColumnsInfo(Collection $columns, Collection $foreignKeys, Collection $indexes): void
    {
        foreach ($columns as $column) {
            $this->setColumnInfo($column, $foreignKeys, $indexes);
        }
    }

    /**
     * @param  Collection<ForeignKey>  $foreignKeys
     * @param  Collection<Index>  $indexes
     */
    private function setColumnInfo(Column $column, Collection $foreignKeys, Collection $indexes): void
    {
        $remapped_fks = [];

        foreach ($foreignKeys as $fk) {
            foreach ($fk->localColumnNames() as $idx => $lc) {
                $remapped_fks[$lc] = [$fk->foreignTable, $fk->foreignColumnNames()[$idx]];
            }
        }

        $remapped_uids = [];

        foreach ($indexes as $idx) {
            if (count($idx->columns) === 1 && ($idx->isPrimaryKey() || $idx->isUnique())) {
                $remapped_uids[] = $idx->columns[0];
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
        if (! isset($this->inspected_rules[self::DEFAULT_RULE])) {
            $this->inspected_rules[self::DEFAULT_RULE] = [];
        }
        $type_rule = $is_date ? 'date' : self::columnTypeToValidationRule($column->type->value);
        $this->inspected_rules[self::DEFAULT_RULE][$column->name] = [$type_rule];

        $soft_delete = in_array($column->name, ['deleted', 'deleted_at', 'deletedAt'], true) && $this->forceDeleting;

        if (! $column->isUnsigned()) {
            $this->inspected_rules[self::DEFAULT_RULE][$column->name][] = 'min:0';
        }

        if (! $column->isNullable() && ! $column->isAutoincrement()) {
            $this->inspected_rules[self::DEFAULT_RULE][$column->name][] = 'required';
        }

        if ($column->getLength() && $column->type->value === 'string') {
            $this->inspected_rules[self::DEFAULT_RULE][$column->name][] = 'max:' . $column->getLength();
        }

        if (array_key_exists($column->name, $remapped_fks)) {
            $this->inspected_rules[self::DEFAULT_RULE][$column->name][] = sprintf('exists:%s.%s,%s', $this->connection ?? 'default', $remapped_fks[$column->name][0], $remapped_fks[$column->name][1]);
        }

        if (in_array($column->name, $remapped_uids, true)) {
            $this->inspected_rules[self::DEFAULT_RULE][$column->name][] = Rule::unique($this->table)->where(function ($query) use ($soft_delete): void {
                if ($soft_delete) {
                    $query->whereNull('deleted_at');
                }
            });
        }

        if (in_array($column->name, ['deleted', 'deleted_at', 'deletedAt'], true) && $this->forceDeleting) {
            $this->forceDeleting = false;
        }
    }

    public function getRules(): array
    {
        $rules = $this->getRulesTrait();

        if ($this->inspected_rules !== []) {
            $rules[self::DEFAULT_RULE] = array_merge($rules[self::DEFAULT_RULE] ?? [], $this->inspected_rules[self::DEFAULT_RULE] ?? []);
        }

        return $rules;
    }

    /**
     * Map Inspector/Doctrine column type to a Laravel validation rule (Laravel has no "unknown", "blob", etc.).
     */
    private static function columnTypeToValidationRule(string $typeValue): string
    {
        $laravel_rules = ['string', 'integer', 'numeric', 'boolean', 'array', 'date', 'json'];

        return in_array($typeValue, $laravel_rules, true) ? $typeValue : 'string';
    }

    /**
     * @param  Collection<ForeignKey>  $foreignKeys
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
     * @throws Exception
     */
    private function setReverseRelationsInfo(Request $request): void
    {
        if (! $request->has('relations')) {
            return;
        }

        foreach ($request->get('relations') as $relation_name) {
            $this->setReverseRelationInfo($relation_name);
        }
    }

    /**
     * @throws Exception
     */
    private function setReverseRelationInfo(string $relationName): void
    {
        $resolved_model = self::resolve($relationName, $this->getConnectionName());
        $reverse_relations = $resolved_model->getDynamicRelations();

        foreach ($reverse_relations as $relation => $relation_data) {
            if ($relation === $this->table) {
                $table = $resolved_model->getTable();
                $this->dynamic_relations[$table] = [
                    'type' => 'belongsToMany',
                    'foreignKey' => new ForeignKey(
                        'reversed_' . $relation_data->name,
                        $relation_data->foreignColumns,
                        $relation_data->localSchema,
                        $relation_data->localConnection,
                        $relation_data->columns,
                        $resolved_model->getConnectionName(),
                        $table,
                    ),
                ];

                break;
            }
        }
    }
}
