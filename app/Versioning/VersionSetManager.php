<?php

declare(strict_types=1);

namespace Modules\Core\Versioning;

use Closure;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;
use Modules\Core\Enums\VersionSetKind;
use Modules\Core\Models\VersionSet;
use Modules\Core\Versioning\Contracts\VersionSetManagerInterface;
use Modules\Core\Versioning\Data\VersionSetOptions;
use Modules\Core\Versioning\Data\VersionSetRoot;
use Modules\Core\Versioning\Exceptions\DirtyActiveVersionSetRootException;
use Modules\Core\Versioning\Exceptions\InvalidRevertedVersionSetException;
use Modules\Core\Versioning\Exceptions\MultipleVersionConnectionsNotSupportedException;
use Modules\Core\Versioning\Exceptions\VersionSetOptionsMismatchException;
use Modules\Core\Versioning\Exceptions\VersionSetRootMismatchException;
use Override;

final class VersionSetManager implements VersionSetManagerInterface
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly ActiveVersionSet $active,
    ) {}

    #[Override]
    public function run(
        VersionSetRoot $root,
        Closure $operation,
        ?VersionSetOptions $options = null,
    ): mixed {
        if ($this->active->isActive()) {
            return $this->runNested($root, $operation, $options);
        }

        $connection = $root->model()->getConnection();
        $connection_name = $connection->getName();
        $resolved_options = $options ?? new VersionSetOptions;
        $this->active->activate($root, $connection_name, $resolved_options);

        try {
            $this->enlist($connection_name);

            return $connection->transaction(function () use ($root, $operation, $resolved_options): mixed {
                $this->lockExistingRoot($root);
                $this->validateRevertedFrom($root, $resolved_options);
                $result = $operation($this->active);
                $this->enlist($root->connectionName());
                $this->active->finish();

                return $result;
            });
        } finally {
            $this->active->reset();
        }
    }

    #[Override]
    public function enlist(string $connection): void
    {
        if (! $this->active->isActive()) {
            throw new LogicException('A database connection can only be enlisted inside a version set scope.');
        }

        $normalized = $this->database->connection($connection)->getName();
        $enlisted = $this->active->enlistedConnectionName();

        if ($enlisted !== null && $enlisted !== $normalized) {
            throw MultipleVersionConnectionsNotSupportedException::forConnections($enlisted, $normalized);
        }

        $this->active->setEnlistedConnectionName($normalized);
    }

    #[Override]
    public function current(): ?ActiveVersionSet
    {
        return $this->active->isActive() ? $this->active : null;
    }

    private function runNested(
        VersionSetRoot $root,
        Closure $operation,
        ?VersionSetOptions $options,
    ): mixed {
        $active_root = $this->active->root();

        if (! $active_root->matches($root)) {
            throw VersionSetRootMismatchException::between($active_root, $root);
        }

        if ($options !== null && ! $this->active->options()->semanticallyEquals($options)) {
            throw new VersionSetOptionsMismatchException;
        }

        $this->enlist($root->connectionName());

        if ($options !== null) {
            $this->validateRevertedFrom($root, $options);
        }

        $this->synchronizeNestedRoot($root);
        $this->active->enterNested();

        try {
            return $operation($this->active);
        } finally {
            $this->active->leaveNested();
        }
    }

    private function lockExistingRoot(VersionSetRoot $root): void
    {
        $model = $root->model();

        if (! $model->exists || $model->getKey() === null) {
            return;
        }

        $dirty_attributes = $model->getDirty();
        $locked = $model->newQueryWithoutScopes()
            ->whereKey($model->getKey())
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw (new ModelNotFoundException)->setModel($model::class, [$model->getKey()]);
        }

        $model->setRawAttributes($locked->getAttributes(), true);
        $model->setRawAttributes(array_replace($model->getAttributes(), $dirty_attributes));
        $model->unsetRelations();
    }

    private function synchronizeNestedRoot(VersionSetRoot $root): void
    {
        $active_model = $this->active->root()->model();
        $nested_model = $root->model();

        if ($active_model === $nested_model) {
            return;
        }

        if ($active_model->isDirty()) {
            throw new DirtyActiveVersionSetRootException;
        }

        $nested_dirty_attributes = $nested_model->getDirty();
        $nested_model->setRawAttributes($active_model->getRawOriginal(), true);
        $nested_model->setRawAttributes(array_replace(
            $active_model->getAttributes(),
            $nested_dirty_attributes,
        ));
        $nested_model->unsetRelations();
    }

    private function validateRevertedFrom(VersionSetRoot $root, VersionSetOptions $options): void
    {
        if ($options->kind !== VersionSetKind::Revert) {
            return;
        }

        $target_id = $options->revertedFromId();

        if ($target_id === null || $root->id() === null || $root->id() === '') {
            if ($target_id !== null) {
                throw InvalidRevertedVersionSetException::wrongRoot($target_id);
            }

            return;
        }

        $connection_name = $this->active->connectionName();
        $input_target = $options->revertedFrom;

        if ($input_target instanceof VersionSet) {
            $target_connection = $input_target->getConnection()->getName();

            if ($target_connection !== $connection_name) {
                throw InvalidRevertedVersionSetException::wrongConnection(
                    $target_id,
                    $connection_name,
                    $target_connection,
                );
            }
        }

        $version_set = new VersionSet;
        $version_set->setConnection($connection_name);
        $target = $version_set->newQuery()->find($target_id);

        if (! $target instanceof VersionSet) {
            throw InvalidRevertedVersionSetException::notFound($target_id, $connection_name);
        }

        if ($target->root_type !== $root->type()
            || $target->root_id !== $root->id()
            || $target->root_connection_ref !== $root->connectionName()
            || $target->root_table_ref !== $root->tableName()) {
            throw InvalidRevertedVersionSetException::wrongRoot($target_id);
        }
    }
}
