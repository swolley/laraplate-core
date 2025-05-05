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
     * @param  Collection<string>  $columns
     * @param  Collection<string>  $foreignColumns
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

    public function isComposite(): bool
    {
        return $this->columns->count() > 1;
    }
}
