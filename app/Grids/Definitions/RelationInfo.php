<?php

declare(strict_types=1);

namespace Modules\Core\Grids\Definitions;

final readonly class RelationInfo
{
    /**
     * @param  string  $type  relation type (method name)
     * @param  string  $name  relation name (from the parent point of view)
     * @param  string  $model  related entity class
     * @param  string  $table  database table
     * @param  string  $foreignKey  db foreign key column
     * @param  string  $ownerKey  db local key (usually primary key)
     */
    public function __construct(private string $type, private string $name, private string $model, private string $table, private string $foreignKey, private string $ownerKey) {}

    /**
     * gets relation type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * gets relation name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * gets related model.
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * gets relation foreign key.
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * get relation owner key.
     */
    public function getOwnerKey(): string
    {
        return $this->ownerKey;
    }

    /**
     * gets related table.
     */
    public function getTable(): string
    {
        return $this->table;
    }
}
