<?php

declare(strict_types=1);

namespace Modules\Core\Inspector\Entities;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Modules\Core\Inspector\Types\DoctrineTypeEnum;

final readonly class Column
{
    public DoctrineTypeEnum $type;

    /**
     * @param  Collection<string>  $attributes
     */
    public function __construct(
        public string $name,
        public Collection $attributes,
        public mixed $default,
        string $type,
    ) {
        $this->type = DoctrineTypeEnum::fromString($type);
    }

    public function isAutoincrement(): bool
    {
        return $this->attributes->contains('autoincrement');
    }

    public function isNullable(): bool
    {
        return $this->attributes->contains('nullable');
    }

    public function isUnsigned(): bool
    {
        return Str::contains($this->type->value, 'unsigned');
    }

    public function getLength(): ?int
    {
        $length = filter_var($this->type->value, FILTER_SANITIZE_NUMBER_INT);

        return $length === [] ? null : (int) $length;
    }
}
