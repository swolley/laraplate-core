<?php

declare(strict_types=1);

namespace Modules\Core\Versioning;

use Closure;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;
use Modules\Core\Versioning\Contracts\VersionSetManagerInterface;
use Modules\Core\Versioning\Data\VersionSetOptions;
use Modules\Core\Versioning\Data\VersionSetRoot;
use Modules\Core\Versioning\Exceptions\MultipleVersionConnectionsNotSupportedException;
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
            return $this->runNested($root, $operation);
        }

        $connection = $root->model()->getConnection();
        $connection_name = $connection->getName();
        $this->active->activate($root, $connection_name, $options ?? new VersionSetOptions);

        try {
            $this->enlist($connection_name);

            return $connection->transaction(function () use ($root, $operation): mixed {
                $this->lockExistingRoot($root);
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

    private function runNested(VersionSetRoot $root, Closure $operation): mixed
    {
        $active_root = $this->active->root();

        if (! $active_root->matches($root)) {
            throw VersionSetRootMismatchException::between($active_root, $root);
        }

        $this->enlist($root->connectionName());
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

        $locked = $model->newQueryWithoutScopes()
            ->whereKey($model->getKey())
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw (new ModelNotFoundException)->setModel($model::class, [$model->getKey()]);
        }
    }
}
