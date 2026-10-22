<?php

declare(strict_types=1);

namespace Modules\Core\Versioning;

use LogicException;
use Modules\Core\Enums\VersionSetKind;
use Modules\Core\Models\VersionSet;
use Modules\Core\Versioning\Data\VersionSetOptions;
use Modules\Core\Versioning\Data\VersionSetRoot;
use Modules\Core\Versioning\Exceptions\PendingVersionSequenceException;
use Modules\Core\Versioning\Exceptions\VersionSequenceMismatchException;

final class ActiveVersionSet
{
    private ?VersionSetRoot $root = null;

    private ?VersionSetOptions $options = null;

    private ?string $connection_name = null;

    private ?string $enlisted_connection_name = null;

    private ?VersionSet $version_set = null;

    /**
     * The kind the set row is stored with when it differs from the scope's options (D53, D60).
     */
    private ?VersionSetKind $stored_kind = null;

    private int $depth = 0;

    private int $sequence = 0;

    private bool $sequence_allocated = false;

    private bool $version_written = false;

    public function activate(
        VersionSetRoot $root,
        string $connection_name,
        VersionSetOptions $options,
    ): void {
        if ($this->isActive()) {
            throw new LogicException('A version set is already active in this scope.');
        }

        $this->root = $root;
        $this->options = $options;
        $this->connection_name = $connection_name;
        $this->depth = 1;
    }

    public function isActive(): bool
    {
        return $this->root !== null;
    }

    public function root(): VersionSetRoot
    {
        return $this->root ?? throw new LogicException('No version set is active.');
    }

    public function options(): VersionSetOptions
    {
        return $this->options ?? throw new LogicException('No version set is active.');
    }

    public function connectionName(): string
    {
        return $this->connection_name ?? throw new LogicException('No version set is active.');
    }

    public function enlistedConnectionName(): ?string
    {
        return $this->enlisted_connection_name;
    }

    public function setEnlistedConnectionName(string $connection_name): void
    {
        $this->enlisted_connection_name = $connection_name;
    }

    public function enterNested(): void
    {
        if (! $this->isActive()) {
            throw new LogicException('No version set is active.');
        }

        $this->depth++;
    }

    public function leaveNested(): void
    {
        if ($this->depth < 2) {
            throw new LogicException('The outer version set scope cannot be left as nested.');
        }

        $this->depth--;
    }

    public function depth(): int
    {
        return $this->depth;
    }

    public function versionSet(): VersionSet
    {
        if ($this->version_set instanceof VersionSet) {
            return $this->version_set;
        }

        $root = $this->root();
        $options = $this->options();
        $version_set = new VersionSet;
        $version_set->setConnection($this->connectionName());
        $version_set->forceFill([
            'uuid' => (string) str()->uuid(),
            'root_type' => $root->type(),
            'root_id' => $root->id(),
            'root_connection_ref' => $root->connectionName(),
            'root_table_ref' => $root->tableName(),
            config('versionable.user_foreign_key', 'user_id') => $options->actorId(),
            'kind' => $this->stored_kind ?? $options->kind,
            'reason' => $options->reason,
            'reverted_from_set_id' => $options->revertedFromId(),
        ])->saveOrFail();
        $this->version_set = $version_set;

        return $version_set;
    }

    /**
     * Stores the set as a baseline: its first row is the creation of its root, a complete anchor (D53). The
     * scope's options keep their kind, so a nested run() with ordinary options still joins it (D60). Too late
     * once the set row exists, and never for a revert.
     */
    public function storeAsBaseline(): void
    {
        if (! $this->version_set instanceof VersionSet && $this->options()->kind === VersionSetKind::Change) {
            $this->stored_kind = VersionSetKind::Baseline;
        }
    }

    public function nextSequence(): int
    {
        $this->versionSet();

        if ($this->sequence_allocated) {
            throw new LogicException('The allocated version sequence must be recorded before requesting another.');
        }

        $this->sequence_allocated = true;

        return $this->sequence + 1;
    }

    public function markVersionWritten(): void
    {
        if (! $this->version_set instanceof VersionSet || ! $this->sequence_allocated) {
            throw new LogicException('A version sequence must be allocated before recording a write.');
        }

        $this->sequence++;
        $this->sequence_allocated = false;
        $this->version_written = true;
    }

    public function finish(): void
    {
        if ($this->sequence_allocated) {
            throw new PendingVersionSequenceException;
        }

        if (! $this->version_set instanceof VersionSet) {
            return;
        }

        $persisted_sequences = $this->version_set->versions()
            ->reorder('sequence')
            ->pluck('sequence')
            ->map(static fn (mixed $sequence): int => (int) $sequence)
            ->values()
            ->all();
        $confirmed_sequences = $this->sequence === 0 ? [] : range(1, $this->sequence);

        if ($persisted_sequences !== $confirmed_sequences) {
            throw new VersionSequenceMismatchException($confirmed_sequences, $persisted_sequences);
        }

        if (! $this->version_written) {
            $this->version_set->deleteOrFail();
            $this->version_set = null;

            return;
        }

        $root = $this->root();
        $root_id = $root->id();

        if ($root_id === null || $root_id === '') {
            throw new LogicException('A non-empty version set requires a persisted aggregate root.');
        }

        $this->version_set->forceFill([
            'root_type' => $root->type(),
            'root_id' => $root_id,
            'root_connection_ref' => $root->connectionName(),
            'root_table_ref' => $root->tableName(),
        ])->saveOrFail();
    }

    public function reset(): void
    {
        $this->root = null;
        $this->options = null;
        $this->connection_name = null;
        $this->enlisted_connection_name = null;
        $this->version_set = null;
        $this->stored_kind = null;
        $this->depth = 0;
        $this->sequence = 0;
        $this->sequence_allocated = false;
        $this->version_written = false;
    }
}
