<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Helpers\HasValidations;
use Modules\Core\Helpers\HasVersions;
use Modules\Core\Inspector\Entities\Column;
use Modules\Core\Inspector\Entities\ForeignKey;
use Modules\Core\Inspector\Entities\Index;
use Modules\Core\Inspector\Inspect;
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
        getRules as protected getRulesTrait;
    }
    use HasVersions;
    use SoftDeletes;

    /**
     * @var bool
     */
    public $timestamps = false;

    private array $dynamic_relations = [];

    private array $dynamic_casts = [];

    /**
     * @throws Exception
     */
    public static function resolve(string $tableName, ?string $connection = null, $attributes = [], ?Request $request = null): Model
    {
        $model = self::tryResolveModel($tableName, $connection);

        if (! in_array($model, [null, '', '0'], true)) {
            return new $model($attributes);
        }

        if (config('crud.dynamic_entities', false)) {
            $cache_key = sprintf('dynamic_entities.%s.%s', $connection ?? 'default', $tableName);

            return Cache::remember($cache_key, null, function () use ($tableName, $connection, $attributes, $request): DynamicEntity {
                $model = new self($attributes);
                $model->inspect($tableName, $connection, $request);

                return $model;
            });
        }

        throw new UnexpectedValueException('Dynamic tables mapping is not enabled');
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

        return $connection === $requestConnection ? $found : null;
    }

    public function getDynamicRelations(): array
    {
        return $this->dynamic_relations;
    }

    public function inspect(string $tableName, ?string $connection = null, ?Request $request = null): void
    {
        $this->setTableConnectionInfo($tableName, $connection);
        $this->verifyTableExistence();

        // TODO: to be tested with oracle and sqlserver
        $inspected = Inspect::table($this->getTable(), $this->getConnectionName());
        $primary_key = $inspected->primaryKey;

        if ($primary_key) {
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
        return array_filter($serialized, fn ($v): bool => gettype($v) !== 'string' || ! (mb_strlen($v) === 60 && preg_match('/^\$2y\$/', $v)));
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
        /** @phpstan-ignore staticMethod.notFound */
        throw_unless(Schema::connection($this->connection)->hasTable($this->table), UnexpectedValueException::class, sprintf("Table '%s' doesn't exists on '%s' connection", $this->table, $this->connection));
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
                $this->incrementing = $first_column->autoincrement;
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
            foreach ($fk->localColumnNames as $idx => $lc) {
                $remapped_fks[$lc] = [$fk->foreignTableName, $fk->foreignColumnNames[$idx]];
            }
        }

        $remapped_uids = [];

        foreach ($indexes as $idx) {
            if (count($idx->columns) === 1 && ($idx->primary || $idx->unique)) {
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

        // validations
        $rules = $this->getRules();
        $rules[self::DEFAULT_RULE][$column->name] = [$is_date ? 'date' : $column->type->value];

        $soft_delete = in_array($column->name, ['deleted', 'deleted_at', 'deletedAt'], true) && $this->forceDeleting;

        if (! $column->isUnsigned()) {
            $rules[self::DEFAULT_RULE][$column->name][] = 'min:0';
        }

        if (! $column->isNullable()) {
            $rules[self::DEFAULT_RULE][$column->name][] = 'required';
        }

        if ($column->getLength() && $column->type->value === 'string') {
            $rules[self::DEFAULT_RULE][$column->name][] = 'max:' . $column->getLength();
        }

        if (array_key_exists($column->name, $remapped_fks)) {
            $rules[self::DEFAULT_RULE][$column->name][] = sprintf('exists:%s.%s,%s', $this->connection ?? 'default', $remapped_fks[$column->name][0], $remapped_fks[$column->name][1]);
        }

        if (in_array($column->name, $remapped_uids, true)) {
            $rules[self::DEFAULT_RULE][$column->name][] = Rule::unique($this->table)->where(function ($query) use ($soft_delete): void {
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
        /** @var DynamicEntity $resolved_model */
        $resolved_model = self::resolve($relationName, $this->getConnectionName());
        $reverse_relations = $resolved_model->getDynamicRelations();

        foreach ($reverse_relations as $relation => $relation_data) {
            if ($relation === $this->table) {
                $this->dynamic_relations[$resolved_model->getTable()] = [
                    'type' => 'belongsToMany',
                    'foreignKey' => new ForeignKey(
                        'reversed_' . $relation_data->name,
                        $relation_data->foreignColumns,
                        $relation_data->localSchema,
                        $relation_data->localConnection,
                        $relation_data->columns,
                        $resolved_model->getConnectionName(),
                        $resolved_model->getTable(),
                    ),
                ];

                break;
            }
        }
    }
}
