<?php

declare(strict_types=1);

namespace Modules\Core\Versioning;

use LogicException;
use Modules\Core\Versioning\Data\VersionSetRoot;

/**
 * What the outermost run() of the version-set manager opened: one transaction on one connection, the version set
 * of every root touched inside it, and the roots named by the run() calls in progress (D43).
 */
final class VersionScope
{
    private ?string $enlisted_connection_name = null;

    /**
     * Every set of the scope, in the order it was opened.
     *
     * @var list<ActiveVersionSet>
     */
    private array $sets = [];

    /**
     * The sets of the roots named by the run() calls in progress, innermost last.
     *
     * @var list<ActiveVersionSet>
     */
    private array $stack = [];

    public function __construct(private readonly string $connection_name) {}

    public function connectionName(): string
    {
        return $this->connection_name;
    }

    public function enlistedConnectionName(): ?string
    {
        return $this->enlisted_connection_name;
    }

    public function setEnlistedConnectionName(string $connection_name): void
    {
        $this->enlisted_connection_name = $connection_name;
    }

    /**
     * The set of a root, found by its identity, or by its instance while it is not saved.
     */
    public function find(VersionSetRoot $root): ?ActiveVersionSet
    {
        foreach ($this->sets as $set) {
            if ($set->root()->matches($root)) {
                return $set;
            }
        }

        return null;
    }

    public function add(ActiveVersionSet $set): void
    {
        $this->sets[] = $set;
    }

    /**
     * @return list<ActiveVersionSet>
     */
    public function sets(): array
    {
        return $this->sets;
    }

    /**
     * The set of the outermost run(), whose options other roots' sets inherit their actor from.
     */
    public function outermost(): ActiveVersionSet
    {
        return $this->sets[0] ?? throw new LogicException('The version scope has no set.');
    }

    public function enter(ActiveVersionSet $set): void
    {
        $this->stack[] = $set;
    }

    public function leave(): void
    {
        array_pop($this->stack);
    }

    public function current(): ?ActiveVersionSet
    {
        return $this->stack === [] ? null : $this->stack[array_key_last($this->stack)];
    }
}
