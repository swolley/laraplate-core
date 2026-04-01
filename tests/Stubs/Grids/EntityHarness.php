<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\IParsableRequest;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Grids\Casts\GridRequestData;
use Modules\Core\Grids\Components\Field;
use Modules\Core\Grids\Definitions\Entity;
use Modules\Core\Grids\Definitions\Relation;
use Modules\Core\Helpers\ResponseBuilder;

final class EntityHarness extends Entity
{
    public static function applyWhere(\Illuminate\Database\Eloquent\Builder $query, string $field, FilterOperator $operator, mixed $value, WhereClause $clause = WhereClause::AND): void
    {
        self::applyCorrectWhereMethod($query, $field, $operator, $value, $clause);
    }

    public function seedField(Field $field): bool
    {
        return $this->addField($field);
    }

    public function seedRelation(Relation $relation): bool
    {
        return $this->addRelation($relation);
    }

    public function removeRelationByName(string $name): bool
    {
        return $this->removeRelationDeeply($name);
    }

    public function hasDeepRelationsPublic(): bool
    {
        return $this->hasDeepRelations();
    }

    /**
     * @return array<int, string>
     */
    public function getTimestampsColumnsPublic(): array
    {
        return $this->getTimestampsColumns();
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, array{property:mixed,direction:'asc'}>
     */
    public function defaultSortsPublic(array $columns, Model $model): array
    {
        return $this->getDefaultSorts($columns, $model);
    }

    public function removeUnusedRelationsPublic(): bool
    {
        return $this->removeUnusedRelations();
    }

    public function setFieldsPublic(iterable $fields): void
    {
        $this->setFields($fields);
    }

    public function addFieldsPublic(iterable $fields): void
    {
        $this->addFields($fields);
    }

    public function getPrimaryKeyPublic(): string|array
    {
        return $this->getPrimaryKey();
    }

    public function getFullPrimaryKeyPublic(): string|array
    {
        return $this->getFullPrimaryKey();
    }

    public function isCurrentEntityPublic(string $name): bool
    {
        return $this->isCurrentEntity($name);
    }

    public function setPathAndName(string $path, string $name): void
    {
        $this->path = $path;
        $this->name = $name;
    }

    public function hasSoftDeletePublic(): bool
    {
        return $this->hasSoftDelete();
    }

    /**
     * @return array<mixed|string>
     */
    public function checkColumnsOrGetDefaultsPublic(Model $model, string $valueColumn, ?array $columns): array
    {
        return $this->checkColumnsOrGetDefaults($model, $valueColumn, $columns);
    }

    public function addSortsIntoQueryPublic(\Illuminate\Database\Eloquent\Builder $query, array $sorts): void
    {
        $this->addSortsIntoQuery($query, $sorts);
    }

    public function setDataIntoResponsePublic(ResponseBuilder $builder, \Illuminate\Support\Collection $data, int $total): void
    {
        $this->setDataIntoResponse($builder, $data, $total);
    }

    public function parseRequestPublic(IParsableRequest $request): void
    {
        /** @var Modules\Core\Grids\Requests\GridRequest $request */
        $this->parseRequest($request);
    }

    public function getRequestDataPublic(): GridRequestData
    {
        return $this->requestData;
    }
}
