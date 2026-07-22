<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Data;

use Illuminate\Database\Eloquent\Model;

final readonly class VersionSetRoot
{
    private function __construct(private Model $model) {}

    public static function forModel(Model $model): self
    {
        return new self($model);
    }

    public function model(): Model
    {
        return $this->model;
    }

    public function type(): string
    {
        return $this->model->getMorphClass();
    }

    public function id(): ?string
    {
        $key = $this->model->getKey();

        return $key === null ? null : (string) $key;
    }

    public function connectionName(): string
    {
        return $this->model->getConnection()->getName();
    }

    public function tableName(): string
    {
        return $this->model->getTable();
    }

    public function matches(self $other): bool
    {
        if ($this->connectionName() !== $other->connectionName()
            || $this->type() !== $other->type()
            || $this->tableName() !== $other->tableName()) {
            return false;
        }

        $id = $this->id();
        $other_id = $other->id();

        if ($id !== null && $other_id !== null) {
            return $id === $other_id;
        }

        return $this->model === $other->model;
    }
}
