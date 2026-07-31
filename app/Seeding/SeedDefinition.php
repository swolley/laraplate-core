<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LogicException;

/**
 * Declares how a set of rows is reconciled.
 *
 * Structural fields follow the code and are realigned on every release.
 * Initial fields are written at creation and never touched again.
 */
final class SeedDefinition
{
    /** @var list<string> */
    public array $identity = [];

    /** @var list<string> */
    public array $structural = [];

    /** @var list<string> */
    public array $initial = [];

    public ?string $module = null;

    /** @var list<array<string,mixed>> */
    public array $rows = [];

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function __construct(public string $modelClass) {}

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function for(string $modelClass): self
    {
        return new self($modelClass);
    }

    /**
     * @param  list<string>  $columns
     */
    public function identity(array $columns): self
    {
        $this->identity = $columns;

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function structural(array $columns): self
    {
        $this->structural = $columns;

        return $this;
    }

    /**
     * @param  list<string>  $columns
     */
    public function initial(array $columns): self
    {
        $this->initial = $columns;

        return $this;
    }

    public function ownedBy(string $module): self
    {
        $this->module = $module;

        return $this;
    }

    /**
     * Empty strings become null here rather than in a saving hook: bulk upserts
     * do not fire Eloquent events, and this is a rule about the data anyway.
     *
     * @param  list<array<string,mixed>>  $rows
     * @throws InvalidArgumentException If a row is missing or has a null/empty identity value.
     */
    public function rows(array $rows): self
    {
        $column = $this->identityColumn();
        $normalized = [];

        foreach ($rows as $index => $row) {
            if (! array_key_exists($column, $row)) {
                throw new InvalidArgumentException(
                    "Row {$index} is missing the identity column '{$column}'.",
                );
            }

            $normalized_row = array_map(
                static fn (mixed $value): mixed => $value === '' ? null : $value,
                $row,
            );

            if (($normalized_row[$column] ?? null) === null) {
                throw new InvalidArgumentException(
                    "Row {$index} is missing a value for the identity column '{$column}'.",
                );
            }

            $normalized[] = $normalized_row;
        }

        $this->rows = $normalized;

        return $this;
    }

    /**
     * Composite identities would require one OR clause per row, defeating the
     * fixed query count the reconciler exists to provide.
     *
     * @throws LogicException If the identity is not exactly one column.
     */
    public function identityColumn(): string
    {
        if (count($this->identity) !== 1) {
            throw new LogicException(
                'SeedReconciler supports a single-column identity only; got '
                . count($this->identity) . ' columns.',
            );
        }

        return $this->identity[0];
    }
}
