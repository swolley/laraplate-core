<?php

declare(strict_types=1);

namespace Modules\Core\Inspector\Entities;

use Illuminate\Support\Collection;

final readonly class ForeignKey
{
    /**
     * @phpstan-ignore property.uninitializedReadonly
     */
    public ?string $foreignConnection;

    /**
     * @param  Collection<int, string>  $columns
     * @param  Collection<int, string>  $foreignColumns
     */
    public function __construct(
        public string $name,
        public Collection $columns,
        public ?string $foreignSchema,
        public string $foreignTable,
        public Collection $foreignColumns,
        public string $localSchema,
        public ?string $localConnection,
        public ?string $onUpdate = null,
        public ?string $onDelete = null,
    ) {
        if ($localSchema === $foreignSchema) {
            $this->foreignConnection = $localConnection;
        } else {
            foreach (config('database.connections') as $name => $config) {
                if ($config['database'] === $foreignSchema) {
                    $this->foreignConnection = $name;

                    break;
                }
            }

            /** @phpstan-ignore property.uninitializedReadonly */
            if (! isset($this->foreignConnection)) {
                /** @phpstan-ignore assign.readOnlyProperty */
                $this->foreignConnection = null;
            }
        }
    }

    /**
     * @return Collection<int, string>
     */
    public function localColumnNames(): Collection
    {
        return $this->columns->map(self::columnName(...));
    }

    /**
     * @return Collection<int, string>
     */
    public function foreignColumnNames(): Collection
    {
        return $this->foreignColumns->map(self::columnName(...));
    }

    /**
     * A column entry is the name itself. Inspectors that hand back objects carrying
     * one are still accepted, which is what {@see \Modules\Core\Inspector\Inspect}
     * never produces and the inspector tests exercise directly.
     */
    private static function columnName(object|string $column): string
    {
        if (is_string($column)) {
            return $column;
        }

        return property_exists($column, 'name') && is_string($column->name) ? $column->name : '';
    }

    public function isComposite(): bool
    {
        return $this->columns->count() > 1;
    }
}
